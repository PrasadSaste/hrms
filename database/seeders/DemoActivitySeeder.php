<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Enums\LeaveStatus;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\WorkCalendar;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Generates three months of plausible attendance, a spread of leave requests
 * and a few announcements so every screen has something to show.
 */
class DemoActivitySeeder extends Seeder
{
    public function __construct(
        protected WorkCalendar $calendar,
        protected LeaveService $leave,
    ) {}

    public function run(): void
    {
        $this->attendance();
        $this->leaveRequests();
        $this->announcements();
    }

    protected function attendance(): void
    {
        $employees = Employee::with(['shift', 'branch'])->active()->get();
        $start = Carbon::today()->subMonthsNoOverflow(3)->startOfMonth();
        $end = Carbon::today();

        foreach ($employees as $employee) {
            $rows = [];
            $cursor = $start->copy();

            // Give each employee a stable but distinct behaviour profile.
            mt_srand($employee->id * 7919);

            while ($cursor->lte($end)) {
                if ($cursor->lt($employee->date_of_joining)) {
                    $cursor->addDay();

                    continue;
                }

                $isWorking = $this->calendar->isWorkingDay($employee, $cursor)
                    && ! $this->calendar->isHoliday($employee->branch_id, $cursor);

                if (! $isWorking) {
                    $cursor->addDay();

                    continue;
                }

                $roll = mt_rand(1, 100);

                // ~4% absent, ~12% late, the rest on time.
                if ($roll <= 4) {
                    $cursor->addDay();

                    continue;
                }

                $late = $roll <= 16;
                $checkInMinute = $late ? mt_rand(50, 105) : mt_rand(-25, 12);
                $checkIn = $cursor->copy()->setTime(9, 30)->addMinutes($checkInMinute);

                $workedMinutes = mt_rand(490, 620);
                $checkOut = $checkIn->copy()->addMinutes($workedMinutes);

                $netWorked = max(0, $workedMinutes - 60);
                $lateMinutes = max(0, $checkInMinute - 15);
                $overtime = max(0, $netWorked - 480);

                $status = match (true) {
                    $netWorked < 240 => AttendanceStatus::HalfDay,
                    $lateMinutes > 0 => AttendanceStatus::Late,
                    default => AttendanceStatus::Present,
                };

                $rows[] = [
                    'employee_id' => $employee->id,
                    'shift_id' => $employee->shift_id,
                    'date' => $cursor->toDateString(),
                    'check_in' => $checkIn->toDateTimeString(),
                    'check_out' => $checkOut->toDateTimeString(),
                    'check_in_ip' => '10.0.'.mt_rand(1, 20).'.'.mt_rand(2, 250),
                    'worked_minutes' => $netWorked,
                    'break_minutes' => 60,
                    'late_minutes' => $lateMinutes,
                    'early_leaving_minutes' => 0,
                    'overtime_minutes' => $overtime,
                    'status' => $status->value,
                    'source' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $cursor->addDay();
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                Attendance::upsert(
                    $chunk,
                    ['employee_id', 'date'],
                    ['check_in', 'check_out', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'status'],
                );
            }
        }

        mt_srand();
    }

    protected function leaveRequests(): void
    {
        $types = LeaveType::active()->whereIn('code', ['CL', 'SL', 'EL'])->get()->keyBy('code');
        $employees = Employee::with('manager.user')->active()->get();
        $approver = User::role(Roles::HR_MANAGER)->first() ?? User::role(Roles::SUPER_ADMIN)->first();

        if ($types->isEmpty() || ! $approver) {
            return;
        }

        // Seed the current year's allocations first so balances make sense.
        $this->leave->allocateYear((int) date('Y'));

        $plans = [
            ['offset' => -45, 'days' => 2, 'code' => 'CL', 'status' => LeaveStatus::Approved, 'reason' => 'Family function out of town.'],
            ['offset' => -20, 'days' => 1, 'code' => 'SL', 'status' => LeaveStatus::Approved, 'reason' => 'Down with fever, resting on doctor advice.'],
            ['offset' => 9, 'days' => 3, 'code' => 'EL', 'status' => LeaveStatus::Pending, 'reason' => 'Planned short holiday with family.'],
            ['offset' => -8, 'days' => 1, 'code' => 'CL', 'status' => LeaveStatus::Rejected, 'reason' => 'Personal errand.'],
        ];

        foreach ($employees as $index => $employee) {
            // Not everyone applies for leave.
            $plan = $plans[$index % count($plans)];

            if ($index % 3 === 2) {
                continue;
            }

            $type = $types->get($plan['code']);

            if (! $type || ! $type->isApplicableTo($employee)) {
                continue;
            }

            $start = Carbon::today()->addDays($plan['offset'] + ($index % 4));
            $end = $start->copy()->addDays($plan['days'] - 1);

            if ($start->lt($employee->date_of_joining)) {
                continue;
            }

            $workingDays = $this->calendar->workingDayCount($employee, $start, $end);

            if ($workingDays <= 0) {
                continue;
            }

            $exists = LeaveRequest::where('employee_id', $employee->id)
                ->overlapping($start->toDateString(), $end->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            $request = LeaveRequest::create([
                'reference' => 'LV-'.$start->format('Ym').'-'.Str::upper(Str::random(6)),
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'day_type' => DayType::FullDay,
                'total_days' => $workingDays,
                'reason' => $plan['reason'],
                'contact_during_leave' => $employee->phone,
                'status' => $plan['status'],
                'applied_on' => $start->copy()->subDays(5),
                'approver_id' => $plan['status'] === LeaveStatus::Pending ? null : $approver->id,
                'actioned_at' => $plan['status'] === LeaveStatus::Pending ? null : $start->copy()->subDays(4),
                'approver_remarks' => match ($plan['status']) {
                    LeaveStatus::Approved => 'Approved. Please hand over ongoing work before you leave.',
                    LeaveStatus::Rejected => 'Cannot be spared this week due to a release. Please re-apply for a later date.',
                    default => null,
                },
            ]);

            if ($request->isApproved()) {
                $this->leave->consumeBalance($request->load(['employee', 'leaveType']));
            }
        }
    }

    protected function announcements(): void
    {
        $author = User::role(Roles::HR_MANAGER)->first() ?? User::role(Roles::SUPER_ADMIN)->first();

        if (! $author) {
            return;
        }

        $announcements = [
            [
                'title' => 'Revised hybrid working policy',
                'body' => "From the first of next month we move to a three-day in-office week. Teams should agree their anchor days with their manager and record them in the team calendar.\n\nThe policy document is available on the intranet. Please read it before the town hall.",
                'is_pinned' => true,
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Payroll cut-off moved to the 25th',
                'body' => "Attendance and leave for the month must be finalised by the 25th. Anything recorded after that date will be carried into the following month's payroll.\n\nManagers, please clear pending approvals before the cut-off.",
                'published_at' => now()->subDays(9),
            ],
            [
                'title' => 'Annual health check-up camp',
                'body' => "The on-site health check-up runs for three days at the Bengaluru office. Slots are limited, so book early through the HR portal.\n\nColleagues in Hyderabad and Pune will receive vouchers for a partner clinic.",
                'published_at' => now()->subDays(15),
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::updateOrCreate(
                ['title' => $announcement['title']],
                $announcement + [
                    'status' => 'published',
                    'created_by' => $author->id,
                    'notify_by_email' => false,
                ],
            );
        }
    }
}
