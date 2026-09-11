<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Answers calendar questions for an employee: which days are working days,
 * which are weekends, and which are holidays for their branch.
 */
class WorkCalendar
{
    /** @var array<string, Collection<int, Holiday>> */
    protected array $holidayCache = [];

    /** @var array<int, ?Branch> */
    protected array $branchCache = [];

    /**
     * Resolved fallback shifts, by branch.
     *
     * A report that walks a month for two hundred people asks this question
     * once per person, and the answer only depends on their branch.
     *
     * @var array<string, ?Shift>
     */
    protected array $shiftCache = [];

    /**
     * The employee's branch, remembered so a report that walks a month for two
     * hundred people does not ask the same question two hundred times.
     */
    public function branchFor(Employee $employee): ?Branch
    {
        if (! $employee->branch_id) {
            return null;
        }

        if ($employee->relationLoaded('branch')) {
            return $employee->branch;
        }

        return $this->branchCache[$employee->branch_id] ??= Branch::find($employee->branch_id);
    }

    /** Day-of-week numbers considered working days for this employee. */
    public function workingDaysFor(Employee $employee): array
    {
        $shift = $employee->relationLoaded('shift') ? $employee->shift : $employee->shift()->first();

        if ($shift instanceof Shift && ! empty($shift->working_days)) {
            return $shift->working_days;
        }

        $branch = $this->branchFor($employee);

        if ($branch instanceof Branch && ! empty($branch->working_days)) {
            return $branch->working_days;
        }

        return [1, 2, 3, 4, 5];
    }

    public function isWorkingDay(Employee $employee, Carbon $date): bool
    {
        if (! in_array((int) $date->isoWeekday(), $this->workingDaysFor($employee), true)) {
            return false;
        }

        return ! $this->isSaturdayOff($employee, $date);
    }

    /**
     * Whether this particular Saturday is one of the branch's off Saturdays.
     *
     * A branch that works Saturdays can still take some of them off — the
     * first and third, or the second and fourth — and which one a date is
     * comes from its position in the month: the 17th is the third Saturday
     * because 17 divided by 7, rounded up, is 3.
     */
    public function isSaturdayOff(Employee $employee, Carbon $date): bool
    {
        if ((int) $date->isoWeekday() !== 6) {
            return false;
        }

        $branch = $this->branchFor($employee);

        if (! $branch) {
            return false;
        }

        return in_array($this->weekOfMonth($date), $branch->saturdayOffWeeks(), true);
    }

    /** Which Nth of its weekday a date is within its month: 1 to 5. */
    public function weekOfMonth(Carbon $date): int
    {
        return (int) ceil($date->day / 7);
    }

    public function isWeekend(Employee $employee, Carbon $date): bool
    {
        return ! $this->isWorkingDay($employee, $date);
    }

    /** Holidays for the employee's branch within a date range, keyed by Y-m-d. */
    public function holidaysBetween(?int $branchId, Carbon $from, Carbon $to): Collection
    {
        $key = sprintf('%s:%s:%s', $branchId ?? 'all', $from->toDateString(), $to->toDateString());

        return $this->holidayCache[$key] ??= Holiday::query()
            ->forBranch($branchId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $h) => $h->date->toDateString());
    }

    public function isHoliday(?int $branchId, Carbon $date): bool
    {
        return $this->holidaysBetween($branchId, $date->copy(), $date->copy())
            ->has($date->toDateString());
    }

    /**
     * Count the working days (excluding weekends and holidays) in a period.
     */
    public function workingDayCount(Employee $employee, Carbon $from, Carbon $to): int
    {
        $holidays = $this->holidaysBetween($employee->branch_id, $from, $to);
        $count = 0;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            if ($this->isWorkingDay($employee, $cursor) && ! $holidays->has($cursor->toDateString())) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /** Every date in a period tagged as working / weekend / holiday. */
    public function classifyPeriod(Employee $employee, Carbon $from, Carbon $to): array
    {
        $holidays = $this->holidaysBetween($employee->branch_id, $from, $to);
        $map = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();

            $map[$key] = match (true) {
                $holidays->has($key) => 'holiday',
                ! $this->isWorkingDay($employee, $cursor) => 'weekend',
                default => 'working',
            };

            $cursor->addDay();
        }

        return $map;
    }

    /** Resolve the shift that governs an employee, falling back to the default. */
    public function shiftFor(Employee $employee): ?Shift
    {
        if ($employee->shift_id) {
            return $employee->relationLoaded('shift') ? $employee->shift : $employee->shift()->first();
        }

        return $this->shiftCache['branch:'.($employee->branch_id ?? 'none')] ??= Shift::query()
            ->active()
            ->where(function ($q) use ($employee) {
                $q->where('branch_id', $employee->branch_id)->orWhereNull('branch_id');
            })
            ->orderByDesc('is_default')
            ->orderByRaw('branch_id is null')
            ->first();
    }
}
