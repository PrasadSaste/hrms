<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Services\AttendanceService;
use App\Support\FinancialYear;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        return view('reports.index', [
            'can' => [
                'attendance' => $request->user()->can('reports.attendance'),
                'leave' => $request->user()->can('reports.leave'),
                'payroll' => $request->user()->can('reports.payroll'),
                'employees' => $request->user()->can('reports.employees'),
            ],
        ]);
    }

    /** Monthly attendance matrix: one row per employee with totals. */
    public function attendance(Request $request): View
    {
        abort_unless($request->user()->can('reports.attendance'), 403);

        $month = $this->resolveMonth($request);
        $employees = $this->reportEmployees($request);

        $rows = $employees->map(function (Employee $employee) use ($month) {
            $summary = $this->attendance->monthlySummary(
                $employee,
                (int) $month->format('Y'),
                (int) $month->format('n'),
            );

            return ['employee' => $employee, 'totals' => $summary['totals']];
        });

        return view('reports.attendance', array_merge($this->filterOptions(), [
            'month' => $month,
            'rows' => $rows,
            'aggregate' => [
                'employees' => $rows->count(),
                'avg_attendance' => $rows->count() > 0
                    ? round($rows->avg(fn ($r) => $r['totals']['attendance_percentage']), 1)
                    : 0.0,
                'total_absent' => $rows->sum(fn ($r) => $r['totals']['absent_days']),
                'total_overtime' => round($rows->sum(fn ($r) => $r['totals']['overtime_hours']), 2),
                'total_late' => $rows->sum(fn ($r) => $r['totals']['late_days']),
            ],
        ]));
    }

    public function attendanceExport(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.attendance'), 403);

        $month = $this->resolveMonth($request);
        $employees = $this->reportEmployees($request);

        return response()->streamDownload(function () use ($employees, $month) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee Code', 'Employee', 'Department', 'Branch', 'Working Days',
                'Present', 'Paid Leave', 'Unpaid Leave', 'Absent', 'Late Days',
                'Worked Hours', 'Overtime Hours', 'Attendance %',
            ]);

            foreach ($employees as $employee) {
                $t = $this->attendance->monthlySummary($employee, (int) $month->format('Y'), (int) $month->format('n'))['totals'];

                fputcsv($handle, [
                    $employee->employee_code,
                    $employee->full_name,
                    $employee->department?->name,
                    $employee->branch?->name,
                    $t['working_days'],
                    $t['present_days'],
                    $t['paid_leave_days'],
                    $t['unpaid_leave_days'],
                    $t['absent_days'],
                    $t['late_days'],
                    $t['worked_hours'],
                    $t['overtime_hours'],
                    $t['attendance_percentage'],
                ]);
            }

            fclose($handle);
        }, 'attendance-report-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Leave taken per employee and per type over a period. */
    public function leave(Request $request): View
    {
        abort_unless($request->user()->can('reports.leave'), 403);

        [$from, $to] = $this->resolveRange($request);

        $requests = LeaveRequest::query()
            ->with(['employee.department', 'leaveType'])
            ->approved()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $v)))
            ->when($request->integer('department_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $v)))
            ->when($request->integer('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->get();

        return view('reports.leave', array_merge($this->filterOptions(), [
            'from' => $from,
            'to' => $to,
            'byEmployee' => $requests->groupBy('employee_id')->map(fn ($group) => [
                'employee' => $group->first()->employee,
                'days' => round($group->sum('total_days'), 2),
                'count' => $group->count(),
                'by_type' => $group->groupBy('leave_type_id')->map(fn ($g) => [
                    'type' => $g->first()->leaveType,
                    'days' => round($g->sum('total_days'), 2),
                ])->values(),
            ])->sortByDesc('days')->values(),
            'byType' => $requests->groupBy('leave_type_id')->map(fn ($group) => [
                'type' => $group->first()->leaveType,
                'days' => round($group->sum('total_days'), 2),
                'count' => $group->count(),
            ])->sortByDesc('days')->values(),
            'leaveTypes' => LeaveType::active()->orderBy('name')->get(),
            'totalDays' => round($requests->sum('total_days'), 2),
        ]));
    }

    /** Payroll cost by month, branch and department. */
    public function payroll(Request $request): View
    {
        abort_unless($request->user()->can('reports.payroll'), 403);

        // The business's own year, which starts in April unless the settings
        // screen says otherwise. With January there it is the calendar year and
        // everything below reads exactly as it always did.
        $year = $request->integer('year') ?: FinancialYear::current();
        $start = FinancialYear::start($year);
        $end = FinancialYear::end($year);

        $payslips = Payslip::query()
            ->with(['employee.department', 'employee.branch'])
            ->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->whereHas('employee', fn ($e) => $e->where('branch_id', $v)))
            ->get();

        $byMonth = collect(FinancialYear::months($year))->map(function (Carbon $month) use ($payslips) {
            $slice = $payslips->filter(
                fn (Payslip $p) => $p->period_start->isSameMonth($month),
            );

            return [
                'month' => (int) $month->format('n'),
                // The year is in the label because a financial year spans two
                // of them, and "Jan" alone would be ambiguous.
                'label' => $month->format(FinancialYear::isCalendar() ? 'M' : "M 'y"),
                'employees' => $slice->count(),
                'gross' => round($slice->sum('gross_earnings'), 2),
                'deductions' => round($slice->sum('total_deductions'), 2),
                'net' => round($slice->sum('net_pay'), 2),
            ];
        });

        return view('reports.payroll', array_merge($this->filterOptions(), [
            'year' => $year,
            'yearLabel' => FinancialYear::label($year),
            'period' => $start->format('M Y').' to '.$end->format('M Y'),
            'byMonth' => $byMonth,
            'byDepartment' => $payslips->groupBy(fn (Payslip $p) => $p->employee?->department?->name ?? 'Unassigned')
                ->map(fn ($group, $name) => [
                    'department' => $name,
                    'employees' => $group->pluck('employee_id')->unique()->count(),
                    'net' => round($group->sum('net_pay'), 2),
                ])->sortByDesc('net')->values(),
            'totals' => [
                'gross' => round($payslips->sum('gross_earnings'), 2),
                'deductions' => round($payslips->sum('total_deductions'), 2),
                'net' => round($payslips->sum('net_pay'), 2),
                'slips' => $payslips->count(),
            ],
            'years' => FinancialYear::options(),
        ]));
    }

    /** Headcount, tenure and attrition. */
    public function employees(Request $request): View
    {
        abort_unless($request->user()->can('reports.employees'), 403);

        $branchId = $request->integer('branch_id') ?: null;
        $base = fn () => Employee::query()->forBranch($branchId);

        $active = $base()->active()->onRoll()->with(['department', 'designation', 'branch'])->get();

        // Joiners and leavers "this year" means the business's year, so the
        // figure matches the one finance is looking at.
        $year = FinancialYear::current();
        $from = FinancialYear::start($year);
        $to = FinancialYear::end($year);

        $exitsThisYear = $base()
            ->whereNotNull('date_of_exit')
            ->whereBetween('date_of_exit', [$from->toDateString(), $to->toDateString()])
            ->count();

        $avgHeadcount = max(1, $active->count());

        return view('reports.employees', array_merge($this->filterOptions(), [
            'total' => $active->count(),
            'byDepartment' => $active->groupBy(fn (Employee $e) => $e->department?->name ?? 'Unassigned')
                ->map->count()->sortDesc(),
            'byBranch' => $active->groupBy(fn (Employee $e) => $e->branch?->name ?? 'Unassigned')
                ->map->count()->sortDesc(),
            'byEmploymentType' => $active->groupBy(fn (Employee $e) => $e->employment_type->label())
                ->map->count()->sortDesc(),
            'byStatus' => $active->groupBy(fn (Employee $e) => $e->employment_status->label())
                ->map->count()->sortDesc(),
            'byGender' => $active->groupBy(fn (Employee $e) => $e->gender ? ucfirst($e->gender) : 'Not specified')
                ->map->count()->sortDesc(),
            'tenureBands' => $this->tenureBands($active),
            'newJoiners' => $base()
                ->whereBetween('date_of_joining', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'exits' => $exitsThisYear,
            'yearLabel' => FinancialYear::label($year),
            'attritionRate' => round($exitsThisYear / $avgHeadcount * 100, 1),
            'onProbation' => $active->where('employment_status', EmploymentStatus::Probation)->count(),
        ]));
    }

    // ------------------------------------------------------------- internals

    protected function tenureBands($employees): array
    {
        $bands = ['< 1 year' => 0, '1-2 years' => 0, '2-5 years' => 0, '5+ years' => 0];

        foreach ($employees as $employee) {
            $months = $employee->tenureInMonths();

            match (true) {
                $months < 12 => $bands['< 1 year']++,
                $months < 24 => $bands['1-2 years']++,
                $months < 60 => $bands['2-5 years']++,
                default => $bands['5+ years']++,
            };
        }

        return $bands;
    }

    protected function reportEmployees(Request $request)
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->active()
            ->onRoll()
            ->with(['department', 'branch', 'designation', 'shift'])
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->integer('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('employee_code')
            ->get();
    }

    protected function filterOptions(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
        ];
    }

    protected function resolveMonth(Request $request): Carbon
    {
        return Carbon::createFromDate(
            $request->integer('year') ?: (int) date('Y'),
            $request->integer('month') ?: (int) date('n'),
            1,
        )->startOfMonth();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function resolveRange(Request $request): array
    {
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::today()->startOfYear();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::today()->endOfYear();

        return [$from, $to];
    }
}
