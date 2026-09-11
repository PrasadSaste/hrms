<?php

namespace App\Services;

use App\Enums\DayType;
use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(protected WorkCalendar $calendar) {}

    /**
     * Number of leave days a request consumes: calendar days in the range minus
     * weekends and holidays, adjusted for half-day requests.
     */
    public function calculateDays(Employee $employee, Carbon $start, Carbon $end, DayType $dayType): float
    {
        if ($start->gt($end)) {
            return 0.0;
        }

        $workingDays = $this->calendar->workingDayCount($employee, $start, $end);

        if ($dayType !== DayType::FullDay) {
            return $workingDays > 0 ? 0.5 : 0.0;
        }

        return (float) $workingDays;
    }

    /** The allocation row for an employee/type/year, created on demand. */
    public function allocationFor(Employee $employee, LeaveType $type, ?int $year = null): LeaveAllocation
    {
        $year ??= (int) date('Y');

        return LeaveAllocation::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
            ],
            [
                'allocated_days' => $this->proratedEntitlement($employee, $type, $year),
            ],
        );
    }

    /**
     * Entitlement prorated by joining date so a mid-year joiner does not get a
     * full year of leave on day one.
     *
     * A type that accrues monthly grants nothing up front — the balance is
     * whatever has been credited so far, which is `LeaveAccrualService`'s job,
     * so this returns zero rather than handing over a year in January.
     */
    public function proratedEntitlement(Employee $employee, LeaveType $type, int $year): float
    {
        if ($type->accruesMonthly()) {
            return 0.0;
        }

        $yearStart = Carbon::create($year, 1, 1);
        $yearEnd = Carbon::create($year, 12, 31);

        if ($employee->date_of_joining->gt($yearEnd)) {
            return 0.0;
        }

        if ($employee->date_of_joining->lte($yearStart)) {
            return (float) $type->days_per_year;
        }

        $remainingMonths = 12 - ((int) $employee->date_of_joining->format('n')) + 1;

        return round($type->days_per_year * $remainingMonths / 12, 1);
    }

    /** Leave balance summary for one employee across all applicable types. */
    public function balanceSummary(Employee $employee, ?int $year = null): Collection
    {
        $year ??= (int) date('Y');

        $allocations = LeaveAllocation::where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        return LeaveType::active()->orderBy('name')->get()
            ->filter(fn (LeaveType $type) => $type->isApplicableTo($employee))
            ->map(function (LeaveType $type) use ($allocations, $employee, $year) {
                $allocation = $allocations->get($type->id);

                $allocated = $allocation?->allocated_days ?? $this->proratedEntitlement($employee, $type, $year);
                $carried = $allocation?->carried_forward_days ?? 0.0;
                $used = $allocation?->used_days ?? 0.0;
                $encashed = $allocation?->encashed_days ?? 0.0;

                return [
                    'leave_type' => $type,
                    'allocation' => $allocation,
                    'allocated' => round($allocated, 2),
                    'carried_forward' => round($carried, 2),
                    'entitled' => round($allocated + $carried, 2),
                    'used' => round($used, 2),
                    'encashed' => round($encashed, 2),
                    'pending' => $this->pendingDays($employee, $type, $year),
                    'remaining' => round($allocated + $carried - $used - $encashed, 2),
                ];
            })->values();
    }

    public function pendingDays(Employee $employee, LeaveType $type, int $year): float
    {
        return (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereYear('start_date', $year)
            ->pending()
            ->sum('total_days');
    }

    public function remainingDays(Employee $employee, LeaveType $type, ?int $year = null): float
    {
        $year ??= (int) date('Y');
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        if (! $allocation) {
            return $this->proratedEntitlement($employee, $type, $year);
        }

        return $allocation->remainingDays();
    }

    /**
     * Validate a leave application against policy: applicability, notice period,
     * consecutive-day cap, overlaps and available balance.
     *
     * @throws ValidationException
     */
    public function assertApplicable(Employee $employee, LeaveType $type, Carbon $start, Carbon $end, DayType $dayType, ?int $ignoreRequestId = null): float
    {
        if ($start->gt($end)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        if (! $type->isApplicableTo($employee)) {
            throw ValidationException::withMessages([
                'leave_type_id' => sprintf(
                    '%s is not available to you yet. It requires %d month(s) of service.',
                    $type->name,
                    $type->applicable_after_months,
                ),
            ]);
        }

        if ($dayType !== DayType::FullDay) {
            if (! $type->allow_half_day) {
                throw ValidationException::withMessages([
                    'day_type' => $type->name.' cannot be taken as a half day.',
                ]);
            }

            if (! $start->isSameDay($end)) {
                throw ValidationException::withMessages([
                    'day_type' => 'A half-day request must start and end on the same date.',
                ]);
            }
        }

        if ($type->min_notice_days > 0) {
            $earliest = Carbon::today()->addDays($type->min_notice_days);

            if ($start->lt($earliest)) {
                throw ValidationException::withMessages([
                    'start_date' => sprintf(
                        '%s requires at least %d day(s) notice. The earliest start date is %s.',
                        $type->name,
                        $type->min_notice_days,
                        $earliest->format('d M Y'),
                    ),
                ]);
            }
        }

        $days = $this->calculateDays($employee, $start, $end, $dayType);

        if ($days <= 0) {
            throw ValidationException::withMessages([
                'start_date' => 'The selected dates contain no working days.',
            ]);
        }

        if ($type->max_consecutive_days > 0 && $days > $type->max_consecutive_days) {
            throw ValidationException::withMessages([
                'end_date' => sprintf(
                    '%s allows a maximum of %d consecutive day(s).',
                    $type->name,
                    $type->max_consecutive_days,
                ),
            ]);
        }

        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->when($ignoreRequestId, fn ($q) => $q->whereKeyNot($ignoreRequestId))
            ->overlapping($start->toDateString(), $end->toDateString())
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'You already have a '.$overlap->status->label().
                    ' leave request covering '.$overlap->periodLabel().'.',
            ]);
        }

        $remaining = $this->remainingDays($employee, $type, (int) $start->format('Y'))
            - $this->pendingDays($employee, $type, (int) $start->format('Y'));

        if ($type->is_paid && $days > $remaining) {
            throw ValidationException::withMessages([
                'leave_type_id' => sprintf(
                    'Insufficient balance. You have %s day(s) of %s available but requested %s.',
                    rtrim(rtrim(number_format(max($remaining, 0), 2), '0'), '.'),
                    $type->name,
                    rtrim(rtrim(number_format($days, 2), '0'), '.'),
                ),
            ]);
        }

        return $days;
    }

    /** Create a leave request after validating it against policy. */
    public function apply(Employee $employee, array $data): LeaveRequest
    {
        $type = LeaveType::findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $dayType = DayType::from($data['day_type'] ?? DayType::FullDay->value);

        $days = $this->assertApplicable($employee, $type, $start, $end, $dayType);

        return DB::transaction(function () use ($employee, $type, $start, $end, $dayType, $days, $data) {
            $request = LeaveRequest::create([
                'reference' => $this->nextReference(),
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'day_type' => $dayType,
                'total_days' => $days,
                'reason' => $data['reason'],
                'contact_during_leave' => $data['contact_during_leave'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => $type->requires_approval ? LeaveStatus::Pending : LeaveStatus::Approved,
                'applied_on' => now(),
            ]);

            if (! $type->requires_approval) {
                $request->forceFill(['actioned_at' => now()])->save();
                $this->consumeBalance($request);
            }

            return $request->fresh(['leaveType', 'employee']);
        });
    }

    /** Approve a pending request and deduct the balance. */
    public function approve(LeaveRequest $request, User $approver, ?string $remarks = null): LeaveRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending request can be approved.',
            ]);
        }

        return DB::transaction(function () use ($request, $approver, $remarks) {
            $request->forceFill([
                'status' => LeaveStatus::Approved,
                'approver_id' => $approver->id,
                'actioned_at' => now(),
                'approver_remarks' => $remarks,
            ])->save();

            $this->consumeBalance($request);

            return $request->fresh(['leaveType', 'employee', 'approver']);
        });
    }

    public function reject(LeaveRequest $request, User $approver, string $remarks): LeaveRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending request can be rejected.',
            ]);
        }

        $request->forceFill([
            'status' => LeaveStatus::Rejected,
            'approver_id' => $approver->id,
            'actioned_at' => now(),
            'approver_remarks' => $remarks,
        ])->save();

        return $request->fresh(['leaveType', 'employee', 'approver']);
    }

    /** Cancel a request, restoring the balance when it had been approved. */
    public function cancel(LeaveRequest $request, ?string $reason = null): LeaveRequest
    {
        if (! $request->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This request can no longer be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($request, $reason) {
            $wasApproved = $request->isApproved();

            $request->forceFill([
                'status' => LeaveStatus::Cancelled,
                'cancel_reason' => $reason,
                'actioned_at' => now(),
            ])->save();

            if ($wasApproved) {
                $this->releaseBalance($request);
            }

            return $request->fresh(['leaveType', 'employee']);
        });
    }

    /** Deduct approved days from the matching allocation. */
    public function consumeBalance(LeaveRequest $request): void
    {
        $allocation = $this->allocationFor(
            $request->employee,
            $request->leaveType,
            (int) $request->start_date->format('Y'),
        );

        $allocation->increment('used_days', $request->total_days);
    }

    /** Give days back when an approved request is cancelled. */
    public function releaseBalance(LeaveRequest $request): void
    {
        $allocation = LeaveAllocation::where('employee_id', $request->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', (int) $request->start_date->format('Y'))
            ->first();

        if ($allocation) {
            $allocation->update([
                'used_days' => max(0, round($allocation->used_days - $request->total_days, 2)),
            ]);
        }
    }

    /**
     * Allocate a year's entitlement to every active employee, carrying forward
     * unused days where the leave type permits it.
     *
     * @return int number of allocation rows written
     */
    public function allocateYear(int $year, ?int $branchId = null): int
    {
        $types = LeaveType::active()->get();
        $count = 0;

        Employee::query()
            ->active()
            ->onRoll()
            ->forBranch($branchId)
            ->chunkById(200, function (Collection $employees) use ($types, $year, &$count) {
                foreach ($employees as $employee) {
                    foreach ($types as $type) {
                        if (! $type->isApplicableTo($employee)) {
                            continue;
                        }

                        $carried = 0.0;

                        if ($type->carry_forward) {
                            $previous = LeaveAllocation::where('employee_id', $employee->id)
                                ->where('leave_type_id', $type->id)
                                ->where('year', $year - 1)
                                ->first();

                            if ($previous) {
                                $carried = min($previous->remainingDays(), $type->max_carry_forward_days);
                                $carried = max($carried, 0);
                            }
                        }

                        // A monthly type's allocation is a running total of
                        // what has been credited, so only the carry-forward is
                        // ours to set: overwriting it here would erase months
                        // people have already earned.
                        $values = $type->accruesMonthly()
                            ? ['carried_forward_days' => round($carried, 2)]
                            : [
                                'allocated_days' => $this->proratedEntitlement($employee, $type, $year),
                                'carried_forward_days' => round($carried, 2),
                            ];

                        LeaveAllocation::updateOrCreate(
                            [
                                'employee_id' => $employee->id,
                                'leave_type_id' => $type->id,
                                'year' => $year,
                            ],
                            $values,
                        );

                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Employees on approved leave on a given date. */
    public function onLeaveOn(Carbon $date, ?int $branchId = null): Collection
    {
        return LeaveRequest::with(['employee.department', 'leaveType'])
            ->approved()
            ->overlapping($date->toDateString(), $date->toDateString())
            ->when($branchId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $branchId)))
            ->get();
    }

    protected function nextReference(): string
    {
        return 'LV-'.date('Ym').'-'.Str::upper(Str::random(6));
    }
}
