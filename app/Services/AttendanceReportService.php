<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\BreakReasons;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The attendance reports: breaks, one day in detail, and two month-wide grids.
 *
 * These read the same rows the attendance screens write — sessions and breaks,
 * summarised on the `attendances` row — so a figure here always matches the
 * day it came from. Everything is loaded in a handful of queries regardless of
 * how many people are in the report, because a month for two hundred employees
 * is six thousand cells and cannot afford a query each.
 */
class AttendanceReportService
{
    /** What a monthly grid can show in each cell. */
    public const METRICS = [
        'working' => 'Working time',
        'break' => 'Break time',
        'overtime' => 'Overtime',
        'late' => 'Late by',
    ];

    public function __construct(protected WorkCalendar $calendar) {}

    // ----------------------------------------------------------- break report

    /**
     * Every break taken on one date, newest employee order, one row per break.
     *
     * @return Collection<int, array{employee: Employee, break: AttendanceBreak}>
     */
    public function breaksOn(Collection $employees, Carbon $date): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $keyed = $employees->keyBy('id');

        return AttendanceBreak::query()
            ->whereIn('employee_id', $keyed->keys())
            ->whereBetween('started_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('started_at')
            ->get()
            ->map(fn (AttendanceBreak $break) => [
                'employee' => $keyed->get($break->employee_id),
                'break' => $break,
            ])
            ->filter(fn (array $row) => $row['employee'] !== null)
            ->values();
    }

    /**
     * A month of breaks totalled per employee, split by reason.
     *
     * @return Collection<int, array{employee: Employee, reasons: array<string, int>, total: int, count: int}>
     */
    public function breakTotalsFor(Collection $employees, Carbon $month): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $start = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->endOfDay();

        $breaks = AttendanceBreak::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('started_at', [$start, $end])
            ->get()
            ->groupBy('employee_id');

        return $employees->map(function (Employee $employee) use ($breaks) {
            $taken = $breaks->get($employee->id) ?? collect();

            $reasons = collect(BreakReasons::keys())
                ->mapWithKeys(fn (string $reason) => [
                    $reason => (int) $taken->where('reason', $reason)->sum('duration_minutes'),
                ])
                ->all();

            return [
                'employee' => $employee,
                'reasons' => $reasons,
                'total' => (int) $taken->sum('duration_minutes'),
                'count' => $taken->count(),
            ];
        })->values();
    }

    // -------------------------------------------------- daily attendance

    /**
     * One row per employee for a single date: when they arrived, when they
     * left, from where, and how the day was judged.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dailyAttendance(Collection $employees, Carbon $date): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $attendances = Attendance::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->with(['sessions', 'breaks'])
            ->get()
            ->keyBy('employee_id');

        $leave = $this->leaveBetween($employees, $date, $date);

        return $employees->map(function (Employee $employee) use ($attendances, $leave, $date) {
            $attendance = $attendances->get($employee->id);
            $onLeave = $leave->get($employee->id);
            $shift = $this->calendar->shiftFor($employee);

            return [
                'employee' => $employee,
                'shift' => $shift,
                'attendance' => $attendance,
                'leave' => $onLeave,
                'status' => $this->statusFor($employee, $date, $attendance, $onLeave),
                // Only meaningful once somebody has actually punched.
                'arrival' => $attendance?->check_in
                    ? ($attendance->late_minutes > 0 ? 'late' : 'on-time')
                    : null,
                'departure' => $attendance?->check_out
                    ? ($attendance->early_leaving_minutes > 0 ? 'early' : 'on-time')
                    : null,
            ];
        })->values();
    }

    // ---------------------------------------------------- monthly grids

    /**
     * A month of one figure per employee per day, with each cell told apart as
     * a full day, a short one, absence, leave or a day off.
     *
     * @return array{days: array<int, Carbon>, rows: Collection<int, array<string, mixed>>}
     */
    public function monthlyGrid(Collection $employees, Carbon $month, string $metric = 'working'): array
    {
        $metric = array_key_exists($metric, self::METRICS) ? $metric : 'working';
        $grid = $this->calendarGrid($employees, $month);

        $rows = $grid['rows']->map(function (array $row) use ($metric) {
            $shift = $this->calendar->shiftFor($row['employee']);
            $full = $shift?->fullDayMinutes() ?? 480;
            $half = $shift?->halfDayMinutes() ?? 240;

            $row['cells'] = collect($row['cells'])->map(function (array $cell) use ($metric, $full, $half) {
                $cell['minutes'] = $this->metricMinutes($cell['attendance'], $metric);
                $cell['tone'] = $this->tone($cell, $metric, $full, $half);

                return $cell;
            })->all();

            $row['total'] = collect($row['cells'])->sum('minutes');

            return $row;
        });

        return ['days' => $grid['days'], 'rows' => $rows, 'metric' => $metric];
    }

    /**
     * The same month, but showing the first punch in and the last punch out of
     * each day rather than a total.
     *
     * @return array{days: array<int, Carbon>, rows: Collection<int, array<string, mixed>>}
     */
    public function monthlyInOut(Collection $employees, Carbon $month): array
    {
        $grid = $this->calendarGrid($employees, $month);

        $rows = $grid['rows']->map(function (array $row) {
            $row['cells'] = collect($row['cells'])->map(function (array $cell) {
                $attendance = $cell['attendance'];

                $cell['in'] = $attendance?->check_in;
                $cell['out'] = $attendance?->check_out;
                // Somebody still punched in has an arrival but no departure yet.
                $cell['open'] = $attendance?->check_in !== null && $attendance?->check_out === null;
                $cell['tone'] = $this->tone($cell, 'inout', 0, 0);

                return $cell;
            })->all();

            return $row;
        });

        return ['days' => $grid['days'], 'rows' => $rows];
    }

    // ------------------------------------------------------------- internals

    /**
     * The shared skeleton behind both grids: every day of the month for every
     * employee, already told apart as a working day, a day off or a holiday,
     * with that day's attendance and approved leave attached.
     *
     * @return array{days: array<int, Carbon>, rows: Collection<int, array<string, mixed>>}
     */
    protected function calendarGrid(Collection $employees, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $days[] = $cursor->copy();
        }

        if ($employees->isEmpty()) {
            return ['days' => $days, 'rows' => collect()];
        }

        $attendances = Attendance::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (Attendance $a) => $a->date->toDateString()));

        $leave = $this->leaveBetween($employees, $start, $end, keyed: false);
        $today = Carbon::today();

        $rows = $employees->map(function (Employee $employee) use ($attendances, $leave, $days, $start, $end, $today) {
            $classification = $this->calendar->classifyPeriod($employee, $start->copy(), $end->copy());
            $forEmployee = $attendances->get($employee->id) ?? collect();
            $leaveFor = $leave->get($employee->id) ?? collect();

            $cells = collect($days)->map(function (Carbon $day) use ($classification, $forEmployee, $leaveFor, $today) {
                $key = $day->toDateString();

                return [
                    'date' => $day,
                    'kind' => $classification[$key] ?? 'working',
                    'attendance' => $forEmployee->get($key),
                    'leave' => $leaveFor->first(fn (LeaveRequest $r) => $this->covers($r, $day)),
                    'future' => $day->gt($today),
                ];
            })->all();

            return ['employee' => $employee, 'cells' => $cells];
        })->values();

        return ['days' => $days, 'rows' => $rows];
    }

    /** Approved leave overlapping a period, grouped or keyed by employee. */
    protected function leaveBetween(Collection $employees, Carbon $from, Carbon $to, bool $keyed = true): Collection
    {
        $requests = LeaveRequest::query()
            ->with('leaveType')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get();

        return $keyed ? $requests->keyBy('employee_id') : $requests->groupBy('employee_id');
    }

    protected function covers(LeaveRequest $request, Carbon $day): bool
    {
        return $day->gte($request->start_date) && $day->lte($request->end_date);
    }

    protected function statusFor(
        Employee $employee,
        Carbon $date,
        ?Attendance $attendance,
        ?LeaveRequest $leave,
    ): AttendanceStatus {
        return match (true) {
            $attendance?->check_in !== null => $attendance->status,
            $leave !== null => AttendanceStatus::OnLeave,
            $this->calendar->isHoliday($employee->branch_id, $date) => AttendanceStatus::Holiday,
            ! $this->calendar->isWorkingDay($employee, $date) => AttendanceStatus::Weekend,
            // A working day nobody recorded reads as an absence only where the
            // organisation has said it should.
            AttendanceService::autoAbsent() => AttendanceStatus::Absent,
            default => AttendanceStatus::NotMarked,
        };
    }

    protected function metricMinutes(?Attendance $attendance, string $metric): int
    {
        if (! $attendance) {
            return 0;
        }

        return (int) match ($metric) {
            'break' => $attendance->break_minutes,
            'overtime' => $attendance->overtime_minutes,
            'late' => $attendance->late_minutes,
            default => $attendance->worked_minutes,
        };
    }

    /**
     * How a cell should read at a glance. Only working time is judged against
     * the shift: a green cell for a long break would say the wrong thing.
     */
    protected function tone(array $cell, string $metric, int $full, int $half): string
    {
        if ($cell['kind'] === 'holiday') {
            return 'holiday';
        }

        if ($cell['kind'] === 'weekend') {
            return 'off';
        }

        if ($cell['attendance']?->check_in === null && $cell['leave']) {
            return 'leave';
        }

        if (! $cell['attendance']?->check_in) {
            return $cell['future'] ? 'future' : 'absent';
        }

        // Still punched in: the day has not been judged yet, and a red cell
        // for somebody sitting at their desk would say the wrong thing.
        if ($cell['attendance']->check_out === null) {
            return 'open';
        }

        if ($metric !== 'working') {
            return 'value';
        }

        return match (true) {
            $cell['minutes'] >= $full => 'full',
            $cell['minutes'] >= $half => 'partial',
            default => 'short',
        };
    }
}
