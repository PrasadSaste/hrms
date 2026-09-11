<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceReportService;
use App\Support\BreakReasons;
use App\Support\Duration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The four attendance reports: breaks, one day in detail, and two grids that
 * lay a whole month out across the page.
 *
 * Each one renders a screen and downloads the same rows as CSV, so what
 * somebody exports is exactly what they were looking at, filters and all.
 */
class AttendanceReportController extends Controller
{
    public function __construct(protected AttendanceReportService $reports) {}

    // ----------------------------------------------------------- break report

    public function breaks(Request $request): View
    {
        $this->authorise($request);

        $view = $request->input('view') === 'monthly' ? 'monthly' : 'daily';
        $date = $this->resolveDate($request);
        $month = $this->resolveMonth($request);
        $employees = $this->employees($request);

        return view('reports.breaks', array_merge($this->filterOptions(), [
            'view' => $view,
            'date' => $date,
            'month' => $month,
            'reasons' => BreakReasons::all(),
            'rows' => $view === 'monthly'
                ? $this->reports->breakTotalsFor($employees, $month)
                : $this->reports->breaksOn($employees, $date),
        ]));
    }

    public function breaksExport(Request $request): StreamedResponse
    {
        $this->authorise($request);

        $monthly = $request->input('view') === 'monthly';
        $employees = $this->employees($request);
        $date = $this->resolveDate($request);
        $month = $this->resolveMonth($request);

        if ($monthly) {
            $rows = $this->reports->breakTotalsFor($employees, $month);

            return $this->csv('break-report-'.$month->format('Y-m').'.csv', function ($handle) use ($rows) {
                fputcsv($handle, array_merge(
                    ['Employee Code', 'Employee', 'Department', 'Branch', 'Breaks'],
                    array_values(BreakReasons::options()),
                    ['Total'],
                ));

                foreach ($rows as $row) {
                    fputcsv($handle, array_merge([
                        $row['employee']->employee_code,
                        $row['employee']->full_name,
                        $row['employee']->department?->name,
                        $row['employee']->branch?->name,
                        $row['count'],
                    ], array_map(
                        fn (int $minutes) => Duration::hours($minutes),
                        array_values($row['reasons']),
                    ), [Duration::hours($row['total'])]));
                }
            });
        }

        $rows = $this->reports->breaksOn($employees, $date);

        return $this->csv('break-report-'.$date->toDateString().'.csv', function ($handle) use ($rows) {
            fputcsv($handle, [
                'Employee Code', 'Employee', 'Department', 'Break Type',
                'Break Start', 'Break End', 'Duration (minutes)', 'Comment',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['employee']->employee_code,
                    $row['employee']->full_name,
                    $row['employee']->department?->name,
                    $row['break']->label(),
                    $row['break']->started_at?->format('Y-m-d H:i'),
                    $row['break']->ended_at?->format('Y-m-d H:i'),
                    $row['break']->ended_at ? $row['break']->duration_minutes : null,
                    $row['break']->comment,
                ]);
            }
        });
    }

    // ------------------------------------------------- daily attendance

    public function daily(Request $request): View
    {
        $this->authorise($request);

        $date = $this->resolveDate($request);
        $rows = $this->reports->dailyAttendance($this->employees($request), $date);

        return view('reports.daily-attendance', array_merge($this->filterOptions(), [
            'date' => $date,
            'rows' => $rows,
            'aggregate' => [
                'present' => $rows->filter(fn ($r) => $r['attendance']?->check_in)->count(),
                'absent' => $rows->filter(fn ($r) => ! $r['attendance']?->check_in && ! $r['leave'])->count(),
                'late' => $rows->filter(fn ($r) => $r['arrival'] === 'late')->count(),
                'on_leave' => $rows->filter(fn ($r) => $r['leave'] !== null)->count(),
            ],
        ]));
    }

    public function dailyExport(Request $request): StreamedResponse
    {
        $this->authorise($request);

        $date = $this->resolveDate($request);
        $rows = $this->reports->dailyAttendance($this->employees($request), $date);

        return $this->csv('daily-attendance-'.$date->toDateString().'.csv', function ($handle) use ($rows) {
            fputcsv($handle, [
                'Employee Code', 'Employee', 'Department', 'Shift', 'Arrival', 'In', 'Out',
                'Punch In Location', 'Punch Out Location', 'Departure',
                'Working Time (hours)', 'Break Time (hours)', 'Overtime (hours)', 'Status',
            ]);

            foreach ($rows as $row) {
                $attendance = $row['attendance'];

                fputcsv($handle, [
                    $row['employee']->employee_code,
                    $row['employee']->full_name,
                    $row['employee']->department?->name,
                    $row['shift']?->name,
                    $row['arrival'],
                    $attendance?->check_in?->format('H:i'),
                    $attendance?->check_out?->format('H:i'),
                    $attendance?->check_in_location,
                    $attendance?->check_out_location,
                    $row['departure'],
                    Duration::hours($attendance?->worked_minutes),
                    Duration::hours($attendance?->break_minutes),
                    Duration::hours($attendance?->overtime_minutes),
                    $row['status']->label(),
                ]);
            }
        });
    }

    // --------------------------------------------------- monthly grids

    public function monthly(Request $request): View
    {
        $this->authorise($request);

        $month = $this->resolveMonth($request);
        $page = $this->employeePage($request);
        $grid = $this->reports->monthlyGrid(
            collect($page->items()),
            $month,
            (string) $request->input('metric', 'working'),
        );

        return view('reports.monthly-attendance', array_merge($this->filterOptions(), [
            'month' => $month,
            'days' => $grid['days'],
            'rows' => $grid['rows'],
            'paginator' => $page,
            'metric' => $grid['metric'],
            'metrics' => AttendanceReportService::METRICS,
        ]));
    }

    public function monthlyExport(Request $request): StreamedResponse
    {
        $this->authorise($request);

        $month = $this->resolveMonth($request);
        $grid = $this->reports->monthlyGrid(
            $this->employees($request),
            $month,
            (string) $request->input('metric', 'working'),
        );

        $label = AttendanceReportService::METRICS[$grid['metric']];

        return $this->csv('monthly-attendance-'.$month->format('Y-m').'.csv', function ($handle) use ($grid, $label) {
            fputcsv($handle, array_merge(
                ['Employee Code', 'Employee', 'Department', 'Measure'],
                array_map(fn (Carbon $day) => $day->format('d'), $grid['days']),
                ['Total (hours)'],
            ));

            foreach ($grid['rows'] as $row) {
                fputcsv($handle, array_merge([
                    $row['employee']->employee_code,
                    $row['employee']->full_name,
                    $row['employee']->department?->name,
                    $label,
                ], array_map(
                    fn (array $cell) => $this->cellForCsv($cell),
                    $row['cells'],
                ), [Duration::hours($row['total'])]));
            }
        });
    }

    public function inOut(Request $request): View
    {
        $this->authorise($request);

        $month = $this->resolveMonth($request);
        $page = $this->employeePage($request);
        $grid = $this->reports->monthlyInOut(collect($page->items()), $month);

        return view('reports.monthly-in-out', array_merge($this->filterOptions(), [
            'month' => $month,
            'days' => $grid['days'],
            'rows' => $grid['rows'],
            'paginator' => $page,
        ]));
    }

    public function inOutExport(Request $request): StreamedResponse
    {
        $this->authorise($request);

        $month = $this->resolveMonth($request);
        $grid = $this->reports->monthlyInOut($this->employees($request), $month);

        return $this->csv('monthly-in-out-'.$month->format('Y-m').'.csv', function ($handle) use ($grid) {
            $header = ['Employee Code', 'Employee', 'Department'];
            foreach ($grid['days'] as $day) {
                $header[] = $day->format('d').' In';
                $header[] = $day->format('d').' Out';
            }
            fputcsv($handle, $header);

            foreach ($grid['rows'] as $row) {
                $line = [
                    $row['employee']->employee_code,
                    $row['employee']->full_name,
                    $row['employee']->department?->name,
                ];

                foreach ($row['cells'] as $cell) {
                    $line[] = $cell['in']?->format('H:i') ?? $this->cellForCsv($cell);
                    $line[] = $cell['out']?->format('H:i') ?? ($cell['open'] ? 'Still in' : '');
                }

                fputcsv($handle, $line);
            }
        });
    }

    // ------------------------------------------------------------- internals

    /**
     * A cell that holds no time still has something to say: a day off, a
     * holiday, approved leave, or an absence.
     */
    protected function cellForCsv(array $cell): string
    {
        return match ($cell['tone']) {
            'off' => 'Weekly off',
            'holiday' => 'Holiday',
            'leave' => 'Leave',
            'absent' => 'A',
            'open' => 'Still in',
            'future' => '',
            default => isset($cell['minutes']) ? (string) Duration::hours($cell['minutes']) : '',
        };
    }

    protected function authorise(Request $request): void
    {
        abort_unless($request->user()->can('reports.attendance'), 403);
    }

    /**
     * A month grid is a cell per person per day, so a whole company at once
     * runs to megabytes of markup. The screen shows a page of people at a
     * time; the CSV export still covers everybody.
     */
    protected function employeePage(Request $request): LengthAwarePaginator
    {
        return $this->employeeQuery($request)->paginate(25)->withQueryString();
    }

    /** The people this report covers, within what the viewer may see. */
    protected function employees(Request $request)
    {
        return $this->employeeQuery($request)->get();
    }

    protected function employeeQuery(Request $request): Builder
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->active()
            ->onRoll()
            ->with(['department', 'branch', 'designation', 'shift'])
            ->when($request->integer('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->integer('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('employee_code');
    }

    protected function filterOptions(): array
    {
        return [
            'branches' => Branch::active()->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
        ];
    }

    protected function resolveDate(Request $request): Carbon
    {
        $date = $request->input('date');

        return $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
    }

    protected function resolveMonth(Request $request): Carbon
    {
        return Carbon::createFromDate(
            $request->integer('year') ?: (int) date('Y'),
            $request->integer('month') ?: (int) date('n'),
            1,
        )->startOfMonth();
    }

    protected function csv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $handle = fopen('php://output', 'w');
            $writer($handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
