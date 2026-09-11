<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\PunchRequest;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use App\Support\BreakReasons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    /** Personal attendance: today's punch card plus this month's calendar. */
    public function index(Request $request): View
    {
        $employee = $this->requireEmployee($request);

        $month = $this->resolveMonth($request);
        $summary = $this->attendance->monthlySummary($employee, (int) $month->format('Y'), (int) $month->format('n'));

        return view('attendance.index', [
            'employee' => $employee,
            'month' => $month,
            'summary' => $summary,
            'today' => $this->attendance->todayFor($employee)?->load(['sessions', 'breaks']),
        ]);
    }

    public function checkIn(PunchRequest $request): RedirectResponse
    {
        $this->authorize('punch', Attendance::class);

        $employee = $this->requireEmployee($request);

        $attendance = $this->attendance->checkIn(
            $employee,
            null,
            'web',
            $request->ip(),
            $request->location(),
        );

        $session = $attendance->sessions->last();

        return back()->with('success', 'Punched in at '.$session->started_at->format('h:i A').'.');
    }

    public function checkOut(PunchRequest $request): RedirectResponse
    {
        $this->authorize('punch', Attendance::class);

        $employee = $this->requireEmployee($request);

        $attendance = $this->attendance->checkOut(
            $employee,
            null,
            'web',
            $request->ip(),
            $request->location(),
        );

        $session = $attendance->sessions->last();

        return back()->with('success', sprintf(
            'Punched out at %s. Worked %s today.',
            $session->ended_at->format('h:i A'),
            $this->duration($attendance->worked_minutes * 60),
        ));
    }

    /** Start a break, saying what it is for. */
    public function startBreak(Request $request): RedirectResponse
    {
        $this->authorize('punch', Attendance::class);

        $employee = $this->requireEmployee($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(BreakReasons::keys())],
            'comment' => ['nullable', 'string', 'max:500'],
        ], [], ['reason' => 'break reason']);

        $this->attendance->startBreak(
            $employee,
            $validated['reason'],
            $validated['comment'] ?? null,
        );

        return back()->with('success', BreakReasons::label($validated['reason']).' break started.');
    }

    /** End the break that is running. */
    public function endBreak(Request $request): RedirectResponse
    {
        $this->authorize('punch', Attendance::class);

        $employee = $this->requireEmployee($request);

        $attendance = $this->attendance->endBreak($employee);
        $break = $attendance->breaks->last();

        return back()->with('success', sprintf(
            'Break ended after %s. Back to work.',
            $this->duration($break->duration_minutes * 60),
        ));
    }

    /** Whole seconds as a readable span, e.g. 1h 05m. */
    protected function duration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $minutes)
            : sprintf('%dm', $minutes);
    }

    /** Daily roster across the team or organisation. */
    public function daily(Request $request): View
    {
        $this->authorize('viewAny', Attendance::class);

        $date = $request->date('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->active()
            ->onRoll()
            ->with(['department', 'designation', 'branch'])
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->search($request->string('search')->toString())
            ->orderBy('first_name')
            ->get();

        $roster = $this->attendance->dailyRoster($employees, $date);

        return view('attendance.daily', [
            'date' => $date,
            'roster' => $roster,
            'counts' => $roster->groupBy(fn ($row) => $row['status']->value)->map->count(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'statuses' => AttendanceStatus::options(),
        ]);
    }

    /**
     * The day's punches made further from the branch than it allows.
     *
     * The same list the evening report emails out, scoped to what the person
     * looking may see, so a branch manager reads their own branch and nobody
     * else's.
     */
    public function locationAlerts(Request $request, GeofenceService $geofence): View
    {
        // Deliberately not the same gate as one's own attendance: this screen
        // is about other people, and names the addresses the alerts go to.
        abort_unless($request->user()->canAny(['attendance.view-team', 'attendance.view-all']), 403);

        $date = $request->date('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        return view('attendance.location-alerts', [
            'date' => $date,
            'rows' => $geofence->flaggedPunches($date, $request->user()),
            'enabled' => $geofence->enabled(),
            'defaultRadius' => $geofence->defaultRadius(),
            'unplacedBranches' => $geofence->branchesWithoutCoordinates(),
            'recipients' => $geofence->recipients()->pluck('email'),
        ]);
    }

    /** Monthly attendance sheet for a single employee. */
    public function employee(Request $request, Employee $employee): View
    {
        $this->authorize('viewEmployee', [Attendance::class, $employee]);

        $month = $this->resolveMonth($request);

        return view('attendance.employee', [
            'employee' => $employee->load(['department', 'designation', 'branch', 'shift']),
            'month' => $month,
            'summary' => $this->attendance->monthlySummary($employee, (int) $month->format('Y'), (int) $month->format('n')),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Attendance::class);

        return view('attendance.create', [
            'employees' => Employee::visibleTo($request->user())->active()->orderBy('first_name')->get(),
            'shifts' => Shift::active()->orderBy('name')->get(),
            'statuses' => AttendanceStatus::options(),
            'date' => $request->input('date', Carbon::today()->toDateString()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceStatus::options()))],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $this->authorize('viewEmployee', [Attendance::class, $employee]);

        $this->attendance->record($employee, $validated, $request->user()->id);

        return redirect()->route('attendance.daily', ['date' => $validated['date']])
            ->with('success', 'Attendance recorded for '.$employee->full_name.'.');
    }

    public function edit(Attendance $attendance): View
    {
        $this->authorize('update', $attendance);

        return view('attendance.edit', [
            'attendance' => $attendance->load('employee'),
            'shifts' => Shift::active()->orderBy('name')->get(),
            'statuses' => AttendanceStatus::options(),
        ]);
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        $this->authorize('update', $attendance);

        $validated = $request->validate([
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceStatus::options()))],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['date'] = $attendance->date->toDateString();

        $this->attendance->record($attendance->employee, $validated, $request->user()->id);

        return redirect()->route('attendance.daily', ['date' => $attendance->date->toDateString()])
            ->with('success', 'Attendance updated.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $this->authorize('delete', $attendance);

        $date = $attendance->date->toDateString();
        $attendance->delete();

        return redirect()->route('attendance.daily', ['date' => $date])
            ->with('success', 'Attendance record deleted.');
    }

    /** CSV of the monthly sheet for one employee. */
    public function export(Request $request, Employee $employee): StreamedResponse
    {
        $this->authorize('viewEmployee', [Attendance::class, $employee]);

        $month = $this->resolveMonth($request);
        $summary = $this->attendance->monthlySummary($employee, (int) $month->format('Y'), (int) $month->format('n'));

        $filename = sprintf('attendance-%s-%s.csv', $employee->employee_code, $month->format('Y-m'));

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Day', 'Status', 'Check In', 'Check Out', 'Worked', 'Late (min)', 'Overtime (min)', 'Remarks']);

            foreach ($summary['days'] as $date => $day) {
                $attendance = $day['attendance'];
                fputcsv($handle, [
                    $date,
                    Carbon::parse($date)->format('D'),
                    $day['status']?->label() ?? '-',
                    $attendance?->check_in?->format('H:i') ?? '',
                    $attendance?->check_out?->format('H:i') ?? '',
                    $attendance?->durationLabel() ?? '',
                    $attendance?->late_minutes ?? 0,
                    $attendance?->overtime_minutes ?? 0,
                    $attendance?->remarks ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Punching and the personal sheet need a linked employee record. A pure
     * administrator account has none, so say so instead of failing hard.
     */
    protected function requireEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if(
            $employee === null,
            404,
            'Your user account is not linked to an employee record, so it has no attendance of its own. '
            .'Link it from the employee screen, or use an employee account.',
        );

        return $employee;
    }

    protected function resolveMonth(Request $request): Carbon
    {
        return Carbon::createFromDate(
            $request->integer('year') ?: (int) date('Y'),
            $request->integer('month') ?: (int) date('n'),
            1,
        )->startOfMonth();
    }
}
