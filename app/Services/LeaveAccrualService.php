<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveAccrual;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leave earned a month at a time.
 *
 * A year's entitlement handed over on the first of January is easy to
 * calculate and wrong for anybody who leaves in March. Most employers here
 * credit a fixed number of days each month — commonly 1.5 once somebody is
 * confirmed and 1 while they are on probation — which is what this does.
 *
 * Three things make it safe to run whenever you like:
 *
 * - **Every credit is a row.** A month already credited is skipped, so running
 *   the job twice in a day pays nobody twice.
 * - **It catches up.** A month the server was down for is simply a row that
 *   does not exist yet, and gets written the next time this runs.
 * - **It never takes anything away.** Credits are added to the allocation, so
 *   an opening balance carried over from an old system survives.
 */
class LeaveAccrualService
{
    public function __construct(protected LeaveService $leave) {}

    /**
     * Credit every month that has been earned and not yet paid.
     *
     * @param  Carbon|null  $asOf  the day to reason from, defaulting to today
     * @return array{credited: int, days: float, employees: int, skipped: int}
     */
    public function accrue(?Carbon $asOf = null, ?int $branchId = null, bool $dryRun = false): array
    {
        $asOf ??= Carbon::today();

        $types = LeaveType::active()->get()
            ->filter(fn (LeaveType $type) => $type->accruesMonthly());

        $result = ['credited' => 0, 'days' => 0.0, 'employees' => 0, 'skipped' => 0];

        if ($types->isEmpty()) {
            return $result;
        }

        $touched = [];

        Employee::query()
            ->active()
            ->onRoll()
            ->forBranch($branchId)
            ->chunkById(200, function (Collection $employees) use ($types, $asOf, $dryRun, &$result, &$touched) {
                foreach ($employees as $employee) {
                    foreach ($types as $type) {
                        foreach ($this->monthsToCredit($employee, $type, $asOf) as $month) {
                            $credit = $this->creditFor($employee, $type, $month);

                            if ($credit === null) {
                                $result['skipped']++;

                                continue;
                            }

                            if (! $dryRun) {
                                $this->record($employee, $type, $month, $credit);
                            }

                            $result['credited']++;
                            $result['days'] += $credit['days'];
                            $touched[$employee->id] = true;
                        }
                    }
                }
            });

        $result['days'] = round($result['days'], 2);
        $result['employees'] = count($touched);

        return $result;
    }

    /**
     * The months this employee is owed for and has not been paid.
     *
     * Starts at whichever is later — the year they joined, or the first month
     * this type could have been earned — and stops at the last month that has
     * been earned by `asOf`.
     *
     * @return array<int, Carbon> the first of each month owed
     */
    public function monthsToCredit(Employee $employee, LeaveType $type, Carbon $asOf): array
    {
        $last = $this->lastCreditableMonth($type, $asOf);
        $cursor = $employee->date_of_joining->copy()->startOfMonth();

        // Nothing before the year this type started accruing is worth chasing:
        // an installation that switches a type over in June is not asking for
        // the whole of January to May to appear overnight.
        $floor = $this->startsFrom($type, $asOf);

        if ($cursor->lt($floor)) {
            $cursor = $floor->copy();
        }

        if ($cursor->gt($last)) {
            return [];
        }

        $paid = LeaveAccrual::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where(function ($q) use ($cursor) {
                $q->where('year', '>', $cursor->year)->orWhere(function ($inner) use ($cursor) {
                    $inner->where('year', $cursor->year)->where('month', '>=', $cursor->month);
                });
            })
            ->get()
            ->map(fn (LeaveAccrual $accrual) => $accrual->year.'-'.$accrual->month)
            ->flip();

        $months = [];

        while ($cursor->lte($last)) {
            if (! $paid->has($cursor->year.'-'.$cursor->month)) {
                $months[] = $cursor->copy();
            }

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * What one month is worth to one employee.
     *
     * Returns null when there is nothing to credit — a rate of zero, a month
     * they were not employed for, or a year already at its cap.
     *
     * @return array{days: float, rate: float, basis: string, fraction: float, note: ?string}|null
     */
    public function creditFor(Employee $employee, LeaveType $type, Carbon $month): ?array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // The rate is the one that applied at the end of the month being paid,
        // not the one that applies today.
        $rate = $type->monthlyRateFor($employee, $end);

        if ($rate <= 0) {
            return null;
        }

        $fraction = $this->employedFraction($employee, $start, $end);

        if ($fraction <= 0) {
            return null;
        }

        $days = round($rate * $fraction, 2);

        if ($days <= 0) {
            return null;
        }

        // An annual ceiling, where the type sets one.
        if ($type->days_per_year > 0) {
            $earned = (float) LeaveAccrual::where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('year', $start->year)
                ->sum('days');

            $room = round($type->days_per_year - $earned, 2);

            if ($room <= 0) {
                return null;
            }

            $days = min($days, $room);
        }

        return [
            'days' => $days,
            'rate' => $rate,
            'basis' => $employee->isOnProbation($end)
                ? LeaveAccrual::BASIS_PROBATION
                : LeaveAccrual::BASIS_PERMANENT,
            'fraction' => round($fraction, 4),
            'note' => $fraction < 1 ? 'Part month' : null,
        ];
    }

    /**
     * How much of a month somebody was actually employed for.
     *
     * A joiner on the 20th earns the last eleven days of that month, and
     * somebody who left on the 10th earns the first ten.
     */
    public function employedFraction(Employee $employee, Carbon $start, Carbon $end): float
    {
        $daysInMonth = $end->day;

        // Counted in day numbers rather than by subtracting two timestamps:
        // the end of a month carries a time of 23:59:59, and a difference in
        // days that is really 30.99 rounds its way into paying for a day
        // nobody worked.
        $firstDay = $employee->date_of_joining->between($start, $end)
            ? $employee->date_of_joining->day
            : ($employee->date_of_joining->gt($end) ? null : 1);

        $lastDay = $employee->date_of_exit && $employee->date_of_exit->between($start, $end)
            ? $employee->date_of_exit->day
            : (($employee->date_of_exit && $employee->date_of_exit->lt($start)) ? null : $daysInMonth);

        if ($firstDay === null || $lastDay === null || $firstDay > $lastDay) {
            return 0.0;
        }

        return ($lastDay - $firstDay + 1) / $daysInMonth;
    }

    /** Write the credit, and add it to the employee's balance for that year. */
    protected function record(Employee $employee, LeaveType $type, Carbon $month, array $credit): LeaveAccrual
    {
        return DB::transaction(function () use ($employee, $type, $month, $credit) {
            $accrual = LeaveAccrual::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $month->year,
                'month' => $month->month,
                'days' => $credit['days'],
                'rate' => $credit['rate'],
                'basis' => $credit['basis'],
                'worked_fraction' => $credit['fraction'],
                'note' => $credit['note'],
            ]);

            $allocation = LeaveAllocation::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $month->year,
                ],
                ['allocated_days' => 0],
            );

            // Added rather than recalculated, so an opening balance brought in
            // from an old system is not quietly overwritten.
            $allocation->increment('allocated_days', $credit['days']);

            return $accrual;
        });
    }

    /**
     * The last month that has been earned.
     *
     * A type credited in arrears pays for a month once it is over; one credited
     * in advance pays on the first of the month it covers.
     */
    public function lastCreditableMonth(LeaveType $type, Carbon $asOf): Carbon
    {
        return $type->accrue_in_advance
            ? $asOf->copy()->startOfMonth()
            : $asOf->copy()->startOfMonth()->subMonth();
    }

    /**
     * The earliest month worth crediting.
     *
     * The date on the type, where one is set. Where it is not, only the month
     * that has just been earned: switching a type to monthly in September
     * should not silently hand everybody January to August, and an employer
     * who does want that says so by setting the date.
     */
    protected function startsFrom(LeaveType $type, Carbon $asOf): Carbon
    {
        if ($type->accrual_starts_on) {
            return $type->accrual_starts_on->copy()->startOfMonth();
        }

        return $this->lastCreditableMonth($type, $asOf);
    }

    /** What one employee has been credited this year, most recent first. */
    public function ledgerFor(Employee $employee, ?int $year = null): Collection
    {
        return LeaveAccrual::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year ?? (int) date('Y'))
            ->orderByDesc('month')
            ->get();
    }
}
