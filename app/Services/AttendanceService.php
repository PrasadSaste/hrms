<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\Shift;
use App\Support\BreakReasons;
use App\Support\PunchLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        protected WorkCalendar $calendar,
        protected GeofenceService $geofence,
    ) {}

    /**
     * Punch in, opening a work session.
     *
     * A day can hold as many sessions as somebody needs — out to a client and
     * back again — so this may be called more than once. What it will not do is
     * open a second session while one is already running.
     *
     * @throws ValidationException when they are already punched in.
     */
    public function checkIn(
        Employee $employee,
        ?Carbon $at = null,
        string $source = 'web',
        ?string $ip = null,
        ?PunchLocation $location = null,
    ): Attendance {
        $at ??= Carbon::now();
        $date = $at->copy()->startOfDay();

        $location ??= PunchLocation::none();
        $this->assertLocationAcceptable($location, 'check_in');

        $attendance = $this->rowFor($employee->id, $date);
        $shift = $this->calendar->shiftFor($employee);

        if (! $attendance->exists) {
            $attendance->fill([
                'shift_id' => $shift?->id,
                'source' => $source,
            ])->save();
        }

        $attendance->load('sessions', 'breaks');

        if ($attendance->isPunchedIn()) {
            throw ValidationException::withMessages([
                'check_in' => 'You are already punched in. Punch out before starting another session.',
            ]);
        }

        if ($attendance->isOnBreak()) {
            throw ValidationException::withMessages([
                'check_in' => 'End your break first — you are still punched in for it.',
            ]);
        }

        $session = $attendance->sessions()->create(array_merge([
            'employee_id' => $employee->id,
            'started_at' => $at,
            'check_in_ip' => $ip,
            'source' => $source,
        ], $location->columns('check_in'), $this->geofence->columnsFor('check_in', $employee, $location)));

        // After the punch is saved, so the alert describes a recorded fact.
        $this->geofence->alert($session, 'check_in');

        return $this->rebuild($attendance->fresh(['sessions', 'breaks']), $employee);
    }

    /**
     * Punch out, closing the session that is open.
     *
     * A break still running is closed at the same moment: somebody who walks
     * out at the end of the day should not leave a break counting all night.
     *
     * @throws ValidationException when no session is open.
     */
    public function checkOut(
        Employee $employee,
        ?Carbon $at = null,
        string $source = 'web',
        ?string $ip = null,
        ?PunchLocation $location = null,
    ): Attendance {
        $at ??= Carbon::now();
        $date = $at->copy()->startOfDay();

        $location ??= PunchLocation::none();
        $this->assertLocationAcceptable($location, 'check_out');

        $attendance = Attendance::with(['sessions', 'breaks'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if (! $attendance || $attendance->sessions->isEmpty()) {
            throw ValidationException::withMessages([
                'check_out' => 'You have not punched in today.',
            ]);
        }

        $session = $attendance->openSession();

        if (! $session) {
            throw ValidationException::withMessages([
                'check_out' => 'You are not punched in. Punch in before punching out.',
            ]);
        }

        if ($at->lte($session->started_at)) {
            throw ValidationException::withMessages([
                'check_out' => 'Punch-out time must be after the punch-in time.',
            ]);
        }

        if ($openBreak = $attendance->openBreak()) {
            $this->closeBreak($openBreak, $at);
        }

        $session->fill(array_merge([
            'ended_at' => $at,
            'duration_minutes' => max(0, (int) $session->started_at->diffInMinutes($at)),
            'check_out_ip' => $ip,
        ], $location->columns('check_out'), $this->geofence->columnsFor('check_out', $employee, $location)))->save();

        $this->geofence->alert($session, 'check_out');

        return $this->rebuild($attendance->fresh(['sessions', 'breaks']), $employee);
    }

    /**
     * Start a break, saying what it is for.
     *
     * Breaks sit inside a work session, so somebody has to be punched in to
     * take one. The reason is recorded because an hour of client visits and an
     * hour of lunch are both time away, but only one is worth reporting on.
     *
     * @throws ValidationException when they are not punched in, or already on a break.
     */
    public function startBreak(
        Employee $employee,
        string $reason,
        ?string $comment = null,
        ?Carbon $at = null,
        string $source = 'web',
    ): Attendance {
        $at ??= Carbon::now();

        if (! BreakReasons::exists($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Choose what the break is for.',
            ]);
        }

        $attendance = Attendance::with(['sessions', 'breaks'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', $at->copy()->startOfDay()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->isPunchedIn()) {
            throw ValidationException::withMessages([
                'reason' => 'Punch in before starting a break.',
            ]);
        }

        if ($attendance->isOnBreak()) {
            throw ValidationException::withMessages([
                'reason' => 'You are already on a break. End it before starting another.',
            ]);
        }

        $attendance->breaks()->create([
            'employee_id' => $employee->id,
            'reason' => $reason,
            'comment' => $comment,
            'started_at' => $at,
            'source' => $source,
        ]);

        return $this->rebuild($attendance->fresh(['sessions', 'breaks']), $employee);
    }

    /**
     * End the break that is running.
     *
     * @throws ValidationException when no break is open.
     */
    public function endBreak(Employee $employee, ?Carbon $at = null): Attendance
    {
        $at ??= Carbon::now();

        $attendance = Attendance::with(['sessions', 'breaks'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', $at->copy()->startOfDay()->toDateString())
            ->first();

        $break = $attendance?->openBreak();

        if (! $break) {
            throw ValidationException::withMessages([
                'reason' => 'You are not on a break.',
            ]);
        }

        $this->closeBreak($break, $at);

        return $this->rebuild($attendance->fresh(['sessions', 'breaks']), $employee);
    }

    protected function closeBreak(AttendanceBreak $break, Carbon $at): void
    {
        $break->fill([
            'ended_at' => $at,
            'duration_minutes' => max(0, (int) $break->started_at->diffInMinutes($at)),
        ])->save();
    }

    /**
     * Rewrite the day's summary from its sessions and breaks.
     *
     * The attendances row stays the single thing payroll, the monthly sheet and
     * every report read, so it is kept in step here rather than each of them
     * learning about sessions.
     */
    public function rebuild(Attendance $attendance, ?Employee $employee = null): Attendance
    {
        $employee ??= $attendance->employee;
        $shift = $attendance->shift ?? $this->calendar->shiftFor($employee);

        $sessions = $attendance->sessions;
        $breaks = $attendance->breaks;

        $first = $sessions->first();
        $last = $sessions->last();

        $attendance->check_in = $first?->started_at;
        // The day is only closed once nobody is still punched in.
        $attendance->check_out = $sessions->contains(fn ($s) => $s->isOpen()) ? null : $last?->ended_at;

        if ($first) {
            $attendance->check_in_ip = $first->check_in_ip;
            $attendance->check_in_location = $first->check_in_location;
            $attendance->check_in_latitude = $first->check_in_latitude;
            $attendance->check_in_longitude = $first->check_in_longitude;
            $attendance->check_in_accuracy = $first->check_in_accuracy;
        }

        if ($last && ! $last->isOpen()) {
            $attendance->check_out_ip = $last->check_out_ip;
            $attendance->check_out_location = $last->check_out_location;
            $attendance->check_out_latitude = $last->check_out_latitude;
            $attendance->check_out_longitude = $last->check_out_longitude;
            $attendance->check_out_accuracy = $last->check_out_accuracy;
        }

        $attended = (int) $sessions->sum('duration_minutes');
        $taken = (int) $breaks->sum('duration_minutes');

        // Real breaks replace the shift's nominal unpaid break. Somebody who
        // never touches the break button is treated exactly as before.
        $breakMinutes = $breaks->isNotEmpty() ? $taken : (int) ($shift?->break_minutes ?? 0);
        $worked = max(0, $attended - $breakMinutes);

        $attendance->break_minutes = $breakMinutes;
        $attendance->worked_minutes = $worked;
        $attendance->late_minutes = $attendance->check_in
            ? $this->lateMinutes($shift, $attendance->check_in)
            : 0;
        $attendance->early_leaving_minutes = $attendance->check_out
            ? $this->earlyLeavingMinutes($shift, $attendance->check_out)
            : 0;

        $fullDayMinutes = $shift?->fullDayMinutes() ?? 480;
        $halfDayMinutes = $shift?->halfDayMinutes() ?? 240;
        $attendance->overtime_minutes = max(0, $worked - $fullDayMinutes);

        // While somebody is still working, the day is judged on arrival alone:
        // calling a half day at eleven in the morning would be nonsense.
        $attendance->status = match (true) {
            $attendance->check_out === null => $this->arrivalStatus(
                $shift, $attendance->check_in ?? Carbon::now(), $employee, $attendance->date
            ),
            $worked < $halfDayMinutes => AttendanceStatus::HalfDay,
            $attendance->late_minutes > 0 => AttendanceStatus::Late,
            default => AttendanceStatus::Present,
        };

        $attendance->save();

        return $attendance->refresh()->load(['sessions', 'breaks']);
    }

    public function autoCloseEnabled(): bool
    {
        return (bool) Setting::get('attendance_auto_close_punches', false);
    }

    /**
     * Close punches nobody ever closed, and say so on the day.
     *
     * A forgotten punch-out costs nobody their salary — the day is still
     * counted present, because payroll reads the check-in — but the hours are
     * lost, because a session's duration is only written when it closes. Left
     * alone the session stays open for ever, the card still reads "still
     * working", and the day quietly reports nought hours.
     *
     * So the job closes it, and the shape of that matters:
     *
     * - **At the shift's end, never at the time the job runs.** Somebody who
     *   forgot at six should not be credited until midnight. Where a day has
     *   no shift the session is closed at the moment it opened, crediting no
     *   hours at all rather than inventing a number.
     * - **Never longer than it could have been.** A punch made after the
     *   shift ended closes at the punch, not before it.
     * - **Always flagged.** `needs_correction` is the point of the exercise:
     *   the day has to read as wrong and fixable rather than as a quiet zero,
     *   because only the employee knows when they actually left.
     *
     * Yesterday and earlier only — a session open at four in the afternoon is
     * somebody at work, not somebody who forgot.
     *
     * The switch is checked **here**, not only in the job that calls this.
     * Writing a punch-out nobody made is the thing an organisation has to opt
     * into, so the guard belongs at the point of writing rather than at one of
     * the ways in.
     *
     * @return array<int, Attendance> the days that were closed
     */
    public function closeAbandonedSessions(?Carbon $before = null): array
    {
        if (! $this->autoCloseEnabled()) {
            return [];
        }

        $before = ($before ? $before->copy() : Carbon::today())->startOfDay();

        $sessions = AttendanceSession::query()
            ->whereNull('ended_at')
            ->whereHas('attendance', fn ($q) => $q->whereDate('date', '<', $before->toDateString()))
            ->with(['attendance.shift', 'employee'])
            ->orderBy('id')
            ->get();

        $closed = [];

        foreach ($sessions as $session) {
            $attendance = $session->attendance;

            if (! $attendance) {
                continue;
            }

            $at = $this->abandonedCloseAt($session, $attendance);

            $session->forceFill([
                'ended_at' => $at,
                'auto_closed_at' => Carbon::now(),
                'duration_minutes' => max(0, (int) $session->started_at->diffInMinutes($at)),
            ])->save();

            // A break left running is closed at the same moment, for the same
            // reason: it should not count all night either.
            foreach ($attendance->breaks()->whereNull('ended_at')->get() as $break) {
                $this->closeBreak($break, $at->lt($break->started_at) ? $break->started_at : $at);
            }

            $rebuilt = $this->rebuild($attendance->fresh(['sessions', 'breaks']), $session->employee);
            $rebuilt->forceFill(['needs_correction' => true])->save();

            $closed[] = $rebuilt;
        }

        return $closed;
    }

    /**
     * Where an abandoned session is capped.
     *
     * The shift's end on the day it started, unless the punch itself came
     * later — in which case nothing was worked that the record can vouch for,
     * and the session closes where it opened.
     */
    protected function abandonedCloseAt(AttendanceSession $session, Attendance $attendance): Carbon
    {
        $shift = $attendance->shift ?? $this->calendar->shiftFor($session->employee);
        $started = $session->started_at->copy();

        if (! $shift?->end_time) {
            return $started;
        }

        $end = $started->copy()->setTimeFromTimeString($this->timePart((string) $shift->end_time));

        // A night shift ends the following morning, so an end that reads as
        // before the start belongs to the next day.
        if ($shift->start_time && $this->timePart((string) $shift->end_time) < $this->timePart((string) $shift->start_time)) {
            $end->addDay();
        }

        return $end->gt($started) ? $end : $started;
    }

    /** Manually create or update an attendance record on behalf of an employee. */
    public function record(Employee $employee, array $data, ?int $markedBy = null): Attendance
    {
        $date = Carbon::parse($data['date'])->startOfDay();

        $attendance = $this->rowFor($employee->id, $date);

        $checkIn = ! empty($data['check_in'])
            ? Carbon::parse($date->toDateString().' '.$this->timePart($data['check_in']))
            : null;

        $checkOut = ! empty($data['check_out'])
            ? Carbon::parse($date->toDateString().' '.$this->timePart($data['check_out']))
            : null;

        if ($checkIn && $checkOut && $checkOut->lte($checkIn)) {
            $checkOut->addDay(); // overnight shift
        }

        $attendance->fill([
            'shift_id' => $data['shift_id'] ?? $this->calendar->shiftFor($employee)?->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => $data['status'] ?? AttendanceStatus::Present->value,
            'remarks' => $data['remarks'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'marked_by' => $markedBy,
        ])->save();

        // HR is describing the whole day, so the times they enter replace the
        // sessions rather than sitting beside them. Breaks the employee
        // recorded themselves are left alone.
        $attendance->sessions()->delete();

        if ($checkIn) {
            $attendance->sessions()->create([
                'employee_id' => $employee->id,
                'started_at' => $checkIn,
                'ended_at' => $checkOut,
                'duration_minutes' => $checkOut ? max(0, (int) $checkIn->diffInMinutes($checkOut)) : 0,
                'source' => $data['source'] ?? 'manual',
            ]);
        }

        $attendance = $this->rebuild($attendance->fresh(['sessions', 'breaks']), $employee);

        // An explicit status from HR wins over the computed one.
        if (! empty($data['status'])) {
            $attendance->status = AttendanceStatus::from($data['status']);
        }

        // Somebody has now said what the times were, so the day is no longer
        // an assumption. This is the flag's only way out: an approved
        // correction goes through here, and nothing else clears it.
        $attendance->needs_correction = false;

        $attendance->save();

        return $attendance->refresh();
    }

    /** Today's attendance row for an employee, if any. */
    public function todayFor(Employee $employee): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();
    }

    /**
     * Build a full month summary for one employee: per-day status plus totals.
     *
     * @return array{days: array<string, array<string, mixed>>, totals: array<string, float|int>}
     */
    public function monthlySummary(Employee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        return $this->summaryForPeriod($employee, $start, $end);
    }

    /**
     * Per-day breakdown and totals for any period. This is the single source of
     * truth used by both the attendance report and payroll processing.
     *
     * @return array{days: array<string, array<string, mixed>>, totals: array<string, float|int>}
     */
    public function summaryForPeriod(Employee $employee, Carbon $start, Carbon $end): array
    {
        $classification = $this->calendar->classifyPeriod($employee, $start, $end);

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $a) => $a->date->toDateString());

        $leaveDays = $this->approvedLeaveDays($employee, $start, $end);

        $days = [];
        $totals = [
            'working_days' => 0,
            'elapsed_working_days' => 0,
            'present_days' => 0.0,
            'half_days' => 0,
            'late_days' => 0,
            'absent_days' => 0.0,
            'unmarked_days' => 0.0,
            'paid_leave_days' => 0.0,
            'unpaid_leave_days' => 0.0,
            'holiday_days' => 0,
            'weekend_days' => 0,
            'worked_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
        ];

        $today = Carbon::today();

        foreach ($classification as $date => $kind) {
            $attendance = $attendances->get($date);
            $leave = $leaveDays[$date] ?? null;

            $entry = [
                'date' => $date,
                'kind' => $kind,
                'attendance' => $attendance,
                'leave' => $leave,
                'status' => null,
            ];

            if ($kind === 'holiday') {
                $totals['holiday_days']++;
                $entry['status'] = AttendanceStatus::Holiday;
            } elseif ($kind === 'weekend') {
                $totals['weekend_days']++;
                $entry['status'] = AttendanceStatus::Weekend;
            } else {
                $totals['working_days']++;

                // Days that have actually happened, so a mid-month view is not
                // penalised for the part of the month still ahead of it.
                if (Carbon::parse($date)->lte($today)) {
                    $totals['elapsed_working_days']++;
                }

                if ($leave) {
                    $entry['status'] = AttendanceStatus::OnLeave;
                    $bucket = $leave['is_paid'] ? 'paid_leave_days' : 'unpaid_leave_days';
                    $totals[$bucket] += $leave['portion'];

                    // A half-day leave still expects half a day of attendance.
                    if ($leave['portion'] < 1.0 && $attendance && $attendance->check_in) {
                        $totals['present_days'] += 0.5;
                    } elseif ($leave['portion'] < 1.0) {
                        $totals['absent_days'] += 0.5;
                    }
                } elseif ($attendance && $attendance->check_in) {
                    $entry['status'] = $attendance->status;

                    if ($attendance->status === AttendanceStatus::HalfDay) {
                        $totals['present_days'] += 0.5;
                        $totals['absent_days'] += 0.5;
                        $totals['half_days']++;
                    } else {
                        $totals['present_days'] += 1.0;
                    }

                    if ($attendance->late_minutes > 0) {
                        $totals['late_days']++;
                    }
                } elseif (Carbon::parse($date)->lte($today)) {
                    // Nobody recorded this day. Whether that is an absence or
                    // simply a gap in the paperwork is the organisation's call,
                    // and payroll reads the two buckets differently.
                    if (self::autoAbsent()) {
                        $entry['status'] = AttendanceStatus::Absent;
                        $totals['absent_days'] += 1.0;
                    } else {
                        $entry['status'] = AttendanceStatus::NotMarked;
                        $totals['unmarked_days'] += 1.0;
                    }
                }
            }

            if ($attendance) {
                $totals['worked_minutes'] += $attendance->worked_minutes;
                $totals['overtime_minutes'] += $attendance->overtime_minutes;
                $totals['late_minutes'] += $attendance->late_minutes;
            }

            $days[$date] = $entry;
        }

        $totals['worked_hours'] = round($totals['worked_minutes'] / 60, 2);
        $totals['overtime_hours'] = round($totals['overtime_minutes'] / 60, 2);
        // Measured against elapsed working days so the current month reads
        // correctly; for a finished month the two are the same number.
        $basis = $totals['elapsed_working_days'] ?: $totals['working_days'];
        $totals['attendance_percentage'] = $basis > 0
            ? round(min(100, ($totals['present_days'] + $totals['paid_leave_days']) / $basis * 100), 1)
            : 0.0;

        return ['days' => $days, 'totals' => $totals];
    }

    /**
     * Map of date => ['portion' => float, 'is_paid' => bool, 'request' => LeaveRequest]
     * for approved leave overlapping the period.
     */
    public function approvedLeaveDays(Employee $employee, Carbon $start, Carbon $end): array
    {
        $requests = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($start->toDateString(), $end->toDateString())
            ->get();

        $map = [];

        foreach ($requests as $request) {
            $portion = $request->day_type->factor();

            foreach ($request->datesInRange() as $date) {
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    continue;
                }

                $map[$date] = [
                    'portion' => $portion,
                    'is_paid' => (bool) $request->leaveType?->is_paid,
                    'request' => $request,
                ];
            }
        }

        return $map;
    }

    /** Attendance rows for a whole team on one date, keyed by employee id. */
    public function dailyRoster(Collection $employees, Carbon $date): Collection
    {
        // Sessions come along so the roster can mark a day whose punches were
        // made away from the branch, without a query per row.
        $attendances = Attendance::with('sessions')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        $leaveByEmployee = LeaveRequest::with('leaveType')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', LeaveStatus::Approved->value)
            ->overlapping($date->toDateString(), $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        return $employees->map(function (Employee $employee) use ($attendances, $leaveByEmployee, $date) {
            $attendance = $attendances->get($employee->id);
            $leave = $leaveByEmployee->get($employee->id);

            $status = match (true) {
                $attendance?->check_in !== null => $attendance->status,
                $leave !== null => AttendanceStatus::OnLeave,
                $this->calendar->isHoliday($employee->branch_id, $date) => AttendanceStatus::Holiday,
                ! $this->calendar->isWorkingDay($employee, $date) => AttendanceStatus::Weekend,
                self::autoAbsent() => AttendanceStatus::Absent,
                default => AttendanceStatus::NotMarked,
            };

            return [
                'employee' => $employee,
                'attendance' => $attendance,
                'leave' => $leave,
                'status' => $status,
            ];
        });
    }

    // ------------------------------------------------------------- internals

    protected function arrivalStatus(?Shift $shift, Carbon $at, Employee $employee, Carbon $date): AttendanceStatus
    {
        if ($this->calendar->isHoliday($employee->branch_id, $date)) {
            return AttendanceStatus::Present;
        }

        return $this->lateMinutes($shift, $at) > 0
            ? AttendanceStatus::Late
            : AttendanceStatus::Present;
    }

    protected function lateMinutes(?Shift $shift, Carbon $checkIn): int
    {
        if (! $shift) {
            return 0;
        }

        $expected = $checkIn->copy()->setTimeFromTimeString($this->timePart($shift->start_time))
            ->addMinutes((int) $shift->grace_minutes);

        return $checkIn->gt($expected) ? (int) $expected->diffInMinutes($checkIn) : 0;
    }

    protected function earlyLeavingMinutes(?Shift $shift, Carbon $checkOut): int
    {
        if (! $shift) {
            return 0;
        }

        $expected = $checkOut->copy()->setTimeFromTimeString($this->timePart($shift->end_time));

        return $checkOut->lt($expected) ? (int) $checkOut->diffInMinutes($expected) : 0;
    }

    /**
     * Refuse a punch that carries no position when the organisation requires one.
     *
     * The browser blocks this case first, with a friendlier message. Repeating
     * the check here means the rule also holds for the API and for anyone
     * posting the form directly.
     *
     * @throws ValidationException
     */
    /**
     * Whether a past working day nobody recorded counts as an absence.
     *
     * With this off, the day is "not marked" instead: it is not held against
     * the employee and payroll pays it, on the grounds that a forgotten punch
     * should not quietly cut somebody's salary.
     */
    public static function autoAbsent(): bool
    {
        return (bool) Setting::get('attendance_auto_absent', true);
    }

    /** Whether employees may record their own attendance at all. */
    public static function selfPunchAllowed(): bool
    {
        return (bool) Setting::get('attendance_allow_self_punch', true);
    }

    protected function assertLocationAcceptable(PunchLocation $location, string $field): void
    {
        if (! self::locationRequired() || $location->hasCoordinates()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Your location is required to record attendance. '
                .'Allow location access in your browser and try again.',
        ]);
    }

    /** Whether a punch must carry coordinates. */
    public static function locationRequired(): bool
    {
        return (bool) Setting::get('attendance_require_location', true);
    }

    /**
     * The attendance row for one employee on one date, existing or new.
     * Matching on whereDate keeps this correct whether the driver stores a
     * true DATE (MySQL) or a text value (SQLite).
     */
    protected function rowFor(int $employeeId, Carbon $date): Attendance
    {
        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $date->toDateString())
            ->first();

        return $attendance ?? new Attendance([
            'employee_id' => $employeeId,
            'date' => $date->toDateString(),
        ]);
    }

    /** Accepts "09:30", "09:30:00" or a full datetime and returns H:i:s. */
    protected function timePart(string $value): string
    {
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 5 ? $value.':00' : $value;
        }

        return Carbon::parse($value)->format('H:i:s');
    }
}
