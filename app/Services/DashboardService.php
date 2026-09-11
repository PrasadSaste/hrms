<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PayrollStatus;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected LeaveService $leave,
    ) {}

    /** Everything the dashboard view needs, scoped to what the user may see. */
    public function forUser(User $user): array
    {
        $branchId = $user->scopedBranchId();
        $employee = $user->employee;
        $today = Carbon::today();

        $data = [
            'today' => $today,
            'employee' => $employee,
            'branch_id' => $branchId,
            'announcements' => $this->announcements($employee),
            'holidays' => $this->upcomingHolidays($employee?->branch_id ?? $branchId),
            'birthdays' => $this->upcomingBirthdays($branchId),
            'anniversaries' => $this->upcomingAnniversaries($branchId),
        ];

        // A new joiner whose verification has not cleared sees where they are
        // up to instead of attendance, leave and pay, which are closed to them.
        if ($employee?->awaitingBackgroundCheck()) {
            $data['background_check'] = $employee->backgroundCheck->load('items');
        } elseif ($employee) {
            $data['self'] = $this->selfPanel($employee);
        }

        if ($user->canAny(['attendance.view-all', 'attendance.view-team', 'employees.view'])) {
            $data['org'] = $this->organisationPanel($user, $branchId, $today);
        }

        /*
         * Three dashboards, not one with everything on it. Somebody answerable
         * for other people gets the organisation's shape; somebody answerable
         * only for themselves gets their own. A manager's own fortnight is on
         * My Attendance, where they would go looking for it — putting it here
         * as well only makes the page longer than anybody reads.
         */
        if ($employee && ! isset($data['org']) && isset($data['self'])) {
            $data['mine'] = $this->personalPanel($employee, $today);
        }

        if ($user->can('payroll.view')) {
            $data['payroll'] = $this->payrollPanel($branchId);
        }

        if ($user->can('leave.approve')) {
            $data['pending_leaves'] = $this->pendingApprovals($user, $branchId);
        }

        /*
         * How the request queue is behaving, for whoever is answerable for it.
         * Somebody looking at their own leave does not need a trend line of
         * everybody's, so this is gated on seeing more than one's own.
         */
        if ($user->canAny(['leave.view-all', 'leave.view-team', 'leave.approve'])) {
            $data['requests'] = [
                'trend' => $this->requestTrend($branchId, $today),
                'mix' => $this->requestMix($branchId, $today),
                'speed' => $this->decisionSpeed($branchId, $today),
                'by_type' => $this->leaveByType($branchId, $today),
            ];
        }

        return $data;
    }

    /** Personal panel: today's punch, month attendance, leave balance, latest payslip. */
    public function selfPanel(Employee $employee): array
    {
        $today = Carbon::today();
        $summary = $this->attendance->monthlySummary($employee, (int) $today->format('Y'), (int) $today->format('n'));

        return [
            'today_attendance' => $this->attendance->todayFor($employee)?->load(['sessions', 'breaks']),
            'month_totals' => $summary['totals'],
            'month_days' => $summary['days'],
            'leave_balance' => $this->leave->balanceSummary($employee),
            'pending_requests' => LeaveRequest::with('leaveType')
                ->where('employee_id', $employee->id)
                ->pending()
                ->orderBy('start_date')
                ->get(),
            'latest_payslip' => Payslip::where('employee_id', $employee->id)
                ->published()
                ->orderByDesc('period_start')
                ->first(),
            'upcoming_leave' => LeaveRequest::with('leaveType')
                ->where('employee_id', $employee->id)
                ->approved()
                ->whereDate('end_date', '>=', $today)
                ->orderBy('start_date')
                ->take(3)
                ->get(),
        ];
    }

    /** Headcount and today's attendance mix for the whole organisation or branch. */
    public function organisationPanel(User $user, ?int $branchId, Carbon $today): array
    {
        $employeesQuery = Employee::query()->active()->onRoll()->forBranch($branchId);
        $headcount = (clone $employeesQuery)->count();

        $attendanceToday = Attendance::whereDate('date', $today->toDateString())
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->get();

        $present = $attendanceToday->whereNotNull('check_in')->count();
        $late = $attendanceToday->where('status', AttendanceStatus::Late)->count();
        $onLeave = $this->leave->onLeaveOn($today, $branchId)->count();
        $absent = max(0, $headcount - $present - $onLeave);

        return [
            'headcount' => $headcount,
            'present' => $present,
            'late' => $late,
            'on_leave' => $onLeave,
            'absent' => $absent,
            'attendance_rate' => $headcount > 0 ? round($present / $headcount * 100, 1) : 0.0,
            'branches' => Branch::active()->count(),
            'new_joiners' => (clone $employeesQuery)
                ->whereDate('date_of_joining', '>=', $today->copy()->subDays(30))
                ->count(),
            'exits_this_month' => Employee::query()
                ->forBranch($branchId)
                ->whereNotNull('date_of_exit')
                ->whereYear('date_of_exit', $today->format('Y'))
                ->whereMonth('date_of_exit', $today->format('n'))
                ->count(),
            'pending_leave_count' => LeaveRequest::pending()
                ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
                ->count(),
            'headcount_by_department' => $this->headcountByDepartment($branchId),
            'attendance_trend' => $this->attendanceTrend($branchId, $today),
            'on_leave_today' => $this->leave->onLeaveOn($today, $branchId)->take(8),
        ];
    }

    public function payrollPanel(?int $branchId): array
    {
        $lastRun = Payroll::query()
            ->forBranch($branchId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return [
            'last_run' => $lastRun,
            'pending_runs' => Payroll::query()
                ->forBranch($branchId)
                ->whereIn('status', [PayrollStatus::Draft->value, PayrollStatus::PendingApproval->value])
                ->count(),
            'ytd_net' => (float) Payslip::query()
                ->whereYear('period_start', date('Y'))
                ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
                ->sum('net_pay'),
        ];
    }

    public function pendingApprovals(User $user, ?int $branchId): Collection
    {
        return LeaveRequest::with(['employee.department', 'leaveType'])
            ->pending()
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->when(
                ! $user->can('leave.view-all') && $user->employee,
                fn ($q) => $q->whereHas('employee', function ($e) use ($user) {
                    $e->where('reporting_to', $user->employee->id)
                        ->orWhere('branch_id', $user->employee->branch_id);
                })
            )
            ->orderBy('start_date')
            ->take(10)
            ->get();
    }

    /** Present count for each of the last 14 days. */
    public function attendanceTrend(?int $branchId, Carbon $today, int $days = 14): array
    {
        $from = $today->copy()->subDays($days - 1);

        $rows = Attendance::query()
            ->selectRaw('date, count(*) as total')
            ->whereBetween('date', [$from->toDateString(), $today->toDateString()])
            ->whereNotNull('check_in')
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->groupBy('date')
            ->pluck('total', 'date');

        $trend = [];
        $cursor = $from->copy();

        while ($cursor->lte($today)) {
            $key = $cursor->toDateString();
            $trend[] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $trend;
    }

    /**
     * The same two questions, asked about one person.
     *
     * An employee gets the shape of their own fortnight and the standing of
     * their own requests — the organisation's trend line is neither their
     * business nor any use to them.
     *
     * @return array{attendance: array<int, array<string, mixed>>, mix: array<int, array<string, mixed>>}
     */
    public function personalPanel(Employee $employee, Carbon $today, int $days = 14): array
    {
        $from = $today->copy()->subDays($days - 1);

        $hours = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $today->toDateString()])
            ->pluck('worked_minutes', 'date');

        $attendance = [];
        $cursor = $from->copy();

        while ($cursor->lte($today)) {
            $key = $cursor->toDateString();
            $attendance[] = [
                'label' => $cursor->format('d M'),
                'full' => $cursor->format('l, d F'),
                // Hours rather than a present/absent tick: the shape of a
                // fortnight is the useful thing, and a bar of zero says absent
                // as plainly as a gap would.
                'value' => (int) round(((int) ($hours[$key] ?? 0)) / 60),
            ];
            $cursor->addDay();
        }

        $counts = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereYear('applied_on', $today->year)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $of = fn (LeaveStatus $status) => (int) ($counts[$status->value] ?? 0);

        return [
            'attendance' => $attendance,
            'mix' => [
                ['label' => 'Waiting on a decision', 'value' => $of(LeaveStatus::Pending), 'status' => 'warning', 'icon' => 'clock'],
                ['label' => 'Approved', 'value' => $of(LeaveStatus::Approved), 'status' => 'good', 'icon' => 'check'],
                ['label' => 'Declined', 'value' => $of(LeaveStatus::Rejected), 'status' => 'critical', 'icon' => 'x'],
                ['label' => 'Withdrawn', 'value' => $of(LeaveStatus::Cancelled), 'status' => 'neutral', 'icon' => 'history'],
            ],
        ];
    }

    /**
     * Leave requests raised against leave requests decided, week by week.
     *
     * The two together are the useful shape: raised alone says how busy people
     * are, decided alone says how busy their managers are, and the gap between
     * the lines is the queue growing or shrinking.
     *
     * @return array<int, array{label: string, raised: int, decided: int}>
     */
    public function requestTrend(?int $branchId, Carbon $today, int $weeks = 8): array
    {
        $from = $today->copy()->subWeeks($weeks - 1)->startOfWeek();

        $scope = fn ($query) => $query
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)));

        $raised = $scope(LeaveRequest::query()->whereDate('applied_on', '>=', $from))
            ->get(['applied_on'])
            ->countBy(fn ($row) => $row->applied_on?->copy()->startOfWeek()->toDateString());

        $decided = $scope(LeaveRequest::query()->whereNotNull('actioned_at')->where('actioned_at', '>=', $from))
            ->get(['actioned_at'])
            ->countBy(fn ($row) => $row->actioned_at?->copy()->startOfWeek()->toDateString());

        $trend = [];
        $cursor = $from->copy();

        while ($cursor->lte($today)) {
            $key = $cursor->toDateString();
            $trend[] = [
                'label' => $cursor->format('d M'),
                'raised' => (int) ($raised[$key] ?? 0),
                'decided' => (int) ($decided[$key] ?? 0),
            ];
            $cursor->addWeek();
        }

        return $trend;
    }

    /**
     * Where the requests raised this year currently stand.
     *
     * States rather than categories, so the colours are the reserved status
     * ones and each arrives with an icon and a name — approved-green against
     * rejected-red is not a distinction anybody should have to make by hue.
     *
     * @return array<int, array{label: string, value: int, status: string, icon: string}>
     */
    public function requestMix(?int $branchId, Carbon $today): array
    {
        $counts = LeaveRequest::query()
            ->whereYear('applied_on', $today->year)
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $of = fn (LeaveStatus $status) => (int) ($counts[$status->value] ?? 0);

        return [
            ['label' => 'Awaiting a decision', 'value' => $of(LeaveStatus::Pending), 'status' => 'warning', 'icon' => 'clock'],
            ['label' => 'Approved', 'value' => $of(LeaveStatus::Approved), 'status' => 'good', 'icon' => 'check'],
            ['label' => 'Declined', 'value' => $of(LeaveStatus::Rejected), 'status' => 'critical', 'icon' => 'x'],
            ['label' => 'Withdrawn', 'value' => $of(LeaveStatus::Cancelled), 'status' => 'neutral', 'icon' => 'history'],
        ];
    }

    /**
     * How long a decision takes, on average, over the last ninety days.
     *
     * A single number rather than a chart: there is one thing to know, and a
     * plot of it would be a plot of one value.
     *
     * @return array{days: ?float, decided: int, oldest_waiting: ?int}
     */
    public function decisionSpeed(?int $branchId, Carbon $today): array
    {
        $decided = LeaveRequest::query()
            ->whereNotNull('actioned_at')
            ->whereNotNull('applied_on')
            ->where('actioned_at', '>=', $today->copy()->subDays(90))
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->get(['applied_on', 'actioned_at']);

        /*
         * In PHP rather than SQL: date arithmetic differs between MySQL and the
         * SQLite the suite runs on, and this is a handful of rows either way.
         *
         * Clamped at zero because the difference is signed, and a request
         * imported with a date after its decision would otherwise drag the
         * average below nothing — "decided in minus two days" is not a fact
         * about anything.
         */
        $average = $decided->isEmpty()
            ? null
            : round($decided->avg(fn ($r) => max(0, $r->applied_on->diffInDays($r->actioned_at))), 1);

        $oldest = LeaveRequest::query()
            ->where('status', LeaveStatus::Pending)
            ->whereNotNull('applied_on')
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->orderBy('applied_on')
            ->value('applied_on');

        return [
            'days' => $average,
            'decided' => $decided->count(),
            // Same clamp: something applied for a date still to come has not
            // been waiting a negative number of days, it has been waiting none.
            'oldest_waiting' => $oldest ? max(0, (int) Carbon::parse($oldest)->diffInDays($today)) : null,
        ];
    }

    /**
     * Which kinds of leave are actually being taken this year.
     *
     * @return Collection<int, array{label: string, value: float}>
     */
    public function leaveByType(?int $branchId, Carbon $today): Collection
    {
        return LeaveRequest::query()
            ->where('status', LeaveStatus::Approved)
            ->whereYear('start_date', $today->year)
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->selectRaw('leave_type_id, sum(total_days) as total')
            ->groupBy('leave_type_id')
            ->with('leaveType')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->leaveType?->name ?? 'Unknown',
                'value' => round((float) $row->total, 1),
            ])
            ->sortByDesc('value')
            ->values();
    }

    public function headcountByDepartment(?int $branchId): Collection
    {
        return Employee::query()
            ->active()
            ->onRoll()
            ->forBranch($branchId)
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->with('department')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department?->name ?? 'Unassigned',
                'total' => (int) $row->total,
            ])
            ->sortByDesc('total')
            ->values();
    }

    public function upcomingHolidays(?int $branchId, int $limit = 3): Collection
    {
        return Holiday::forBranch($branchId)
            ->whereDate('date', '>=', Carbon::today())
            ->orderBy('date')
            ->take($limit)
            ->get();
    }

    /** Birthdays in the next 30 days, ignoring the year. */
    public function upcomingBirthdays(?int $branchId, int $days = 30): Collection
    {
        $today = Carbon::today();

        return Employee::query()
            ->active()
            ->onRoll()
            ->forBranch($branchId)
            ->whereNotNull('date_of_birth')
            ->get()
            ->map(function (Employee $employee) use ($today, $days) {
                $next = $employee->date_of_birth->copy()->year($today->year);

                if ($next->lt($today)) {
                    $next->addYear();
                }

                return $next->diffInDays($today) <= $days
                    ? ['employee' => $employee, 'date' => $next, 'in_days' => (int) $today->diffInDays($next)]
                    : null;
            })
            ->filter()
            ->sortBy('in_days')
            ->take(4)
            ->values();
    }

    /** Work anniversaries in the next 30 days. */
    public function upcomingAnniversaries(?int $branchId, int $days = 30): Collection
    {
        $today = Carbon::today();

        return Employee::query()
            ->active()
            ->onRoll()
            ->forBranch($branchId)
            ->get()
            ->map(function (Employee $employee) use ($today, $days) {
                $next = $employee->date_of_joining->copy()->year($today->year);

                if ($next->lt($today)) {
                    $next->addYear();
                }

                $years = $next->year - $employee->date_of_joining->year;

                return ($years > 0 && $next->diffInDays($today) <= $days)
                    ? ['employee' => $employee, 'date' => $next, 'years' => $years, 'in_days' => (int) $today->diffInDays($next)]
                    : null;
            })
            ->filter()
            ->sortBy('in_days')
            ->take(4)
            ->values();
    }

    public function announcements(?Employee $employee, int $limit = 3): Collection
    {
        return Announcement::published()
            ->visibleTo($employee)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->take($limit)
            ->get();
    }
}
