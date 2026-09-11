<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\AttendanceReportService;
use App\Services\AttendanceService;
use App\Support\BreakReasons;
use App\Support\PunchLocation;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The four attendance reports: breaks, one day in detail, and the two month
 * grids.
 */
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A Wednesday, so the punches below land on a working day.
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A full shift: in at half nine, an hour of lunch, out at half six. */
    protected function workADay(Employee $employee, string $date = '2026-06-10'): void
    {
        $service = app(AttendanceService::class);
        $at = fn (string $time) => Carbon::parse($date.' '.$time);

        $service->checkIn($employee, $at('09:30'), 'web', '127.0.0.1', $this->somewhere());
        $service->startBreak($employee, BreakReasons::LUNCH, null, $at('13:00'));
        $service->endBreak($employee, $at('14:00'));
        $service->checkOut($employee, $at('18:30'), 'web', '127.0.0.1', $this->somewhere());
    }

    /** Punches carry a position, so the reports have one to show. */
    protected function somewhere(): PunchLocation
    {
        return new PunchLocation(18.5204, 73.8567, 12);
    }

    // -------------------------------------------------------- break report

    public function test_the_break_report_lists_every_break_taken_on_a_day(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $this->workADay($employee);

        Carbon::setTestNow(Carbon::parse('2026-06-10 19:00:00'));

        $this->actingAs($hr->user)
            ->get(route('reports.breaks', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertSee('Break report')
            ->assertSee('Lunch')
            ->assertSee($employee->full_name)
            ->assertSee('01:00 pm')
            ->assertSee('02:00 pm');
    }

    public function test_the_break_report_totals_a_month_per_employee(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $this->workADay($employee, '2026-06-10');
        $this->workADay($employee, '2026-06-11');

        $rows = app(AttendanceReportService::class)
            ->breakTotalsFor(collect([$employee]), Carbon::parse('2026-06-01'));

        $this->assertSame(120, $rows->first()['total']);
        $this->assertSame(120, $rows->first()['reasons'][BreakReasons::LUNCH]);
        $this->assertSame(2, $rows->first()['count']);
    }

    public function test_a_break_still_running_shows_as_away_rather_than_a_duration(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $service = app(AttendanceService::class);
        $service->checkIn($employee, Carbon::parse('2026-06-10 09:30'), 'web', '127.0.0.1', $this->somewhere());
        $service->startBreak($employee, BreakReasons::CLIENT_VISIT, null, Carbon::parse('2026-06-10 13:00'));

        $this->actingAs($hr->user)
            ->get(route('reports.breaks', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertSee('Client visit')
            ->assertSee('Still away');
    }

    // --------------------------------------------------- daily attendance

    public function test_the_daily_report_shows_arrival_departure_and_working_time(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $this->workADay($employee);

        Carbon::setTestNow(Carbon::parse('2026-06-10 19:00:00'));

        $this->actingAs($hr->user)
            ->get(route('reports.attendance.daily', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertSee($employee->full_name)
            ->assertSee('09:30 am')
            ->assertSee('06:30 pm')
            // Nine hours on the clock, less the hour of lunch.
            ->assertSee('08h 00m');
    }

    public function test_somebody_who_has_not_punched_out_yet_is_shown_as_still_in(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        app(AttendanceService::class)
            ->checkIn($employee, Carbon::parse('2026-06-10 09:30'), 'web', '127.0.0.1', $this->somewhere());

        $this->actingAs($hr->user)
            ->get(route('reports.attendance.daily', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertSee('Still in');
    }

    // ------------------------------------------------------ monthly grids

    public function test_the_monthly_grid_marks_worked_days_days_off_and_absences(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $this->workADay($employee);

        Carbon::setTestNow(Carbon::parse('2026-06-30 19:00:00'));

        $grid = app(AttendanceReportService::class)
            ->monthlyGrid(collect([$employee]), Carbon::parse('2026-06-01'));

        $cells = collect($grid['rows']->first()['cells'])->keyBy(fn ($c) => $c['date']->toDateString());

        $this->assertCount(30, $grid['days']);
        $this->assertSame('full', $cells['2026-06-10']['tone']);
        $this->assertSame(480, $cells['2026-06-10']['minutes']);
        // The 13th is a Saturday, and the 11th a working day nobody turned up for.
        $this->assertSame('off', $cells['2026-06-13']['tone']);
        $this->assertSame('absent', $cells['2026-06-11']['tone']);
    }

    public function test_the_monthly_grid_can_show_break_time_instead(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $this->workADay($employee);

        Carbon::setTestNow(Carbon::parse('2026-06-30 19:00:00'));

        $this->actingAs($hr->user)
            ->get(route('reports.attendance.monthly', ['month' => 6, 'year' => 2026, 'metric' => 'break']))
            ->assertOk()
            ->assertSee('Break time')
            ->assertSee('01h 00m');
    }

    public function test_the_in_out_report_shows_the_first_in_and_last_out(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        // Two sessions in one day: the report shows the outer edges of the day.
        $service = app(AttendanceService::class);
        $at = fn (string $time) => Carbon::parse('2026-06-10 '.$time);

        $service->checkIn($employee, $at('09:00'), 'web', '127.0.0.1', $this->somewhere());
        $service->checkOut($employee, $at('12:00'), 'web', '127.0.0.1', $this->somewhere());
        $service->checkIn($employee, $at('14:00'), 'web', '127.0.0.1', $this->somewhere());
        $service->checkOut($employee, $at('18:30'), 'web', '127.0.0.1', $this->somewhere());

        $grid = app(AttendanceReportService::class)
            ->monthlyInOut(collect([$employee]), Carbon::parse('2026-06-01'));

        $cell = collect($grid['rows']->first()['cells'])
            ->firstWhere(fn ($c) => $c['date']->toDateString() === '2026-06-10');

        $this->assertSame('09:00', $cell['in']->format('H:i'));
        $this->assertSame('18:30', $cell['out']->format('H:i'));
        $this->assertFalse($cell['open']);
    }

    public function test_a_grid_with_nobody_in_it_says_so_instead_of_drawing_an_empty_month(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        foreach (['reports.attendance.monthly', 'reports.attendance.in-out'] as $route) {
            $this->actingAs($hr->user)
                ->get(route($route, ['month' => 6, 'year' => 2026, 'department_id' => 999999]))
                ->assertOk()
                ->assertSee('Nobody to report on')
                // The colour key explains a table that is not there.
                ->assertDontSee('Weekly off');
        }
    }

    public function test_the_grids_page_through_employees_but_the_export_covers_everyone(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $people = collect(range(1, 30))->map(
            fn () => $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch])
        );

        $first = $people->first();
        $last = $people->last();

        // A page of 25, so the thirty-first person is on page two.
        $this->actingAs($hr->user)
            ->get(route('reports.attendance.monthly', ['month' => 6, 'year' => 2026]))
            ->assertOk()
            ->assertSee($first->employee_code)
            ->assertDontSee($last->employee_code);

        $this->actingAs($hr->user)
            ->get(route('reports.attendance.monthly', ['month' => 6, 'year' => 2026, 'page' => 2]))
            ->assertOk()
            ->assertSee($last->employee_code);

        // Downloading is not paged: a spreadsheet wants the whole company.
        $csv = $this->actingAs($hr->user)
            ->get(route('reports.attendance.monthly.export', ['month' => 6, 'year' => 2026]));
        $csv->assertOk();
        $this->assertStringContainsString($first->employee_code, $csv->streamedContent());
        $this->assertStringContainsString($last->employee_code, $csv->streamedContent());
    }

    // ------------------------------------------------------------ access

    public function test_an_employee_without_the_permission_cannot_open_the_reports(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        foreach ([
            'reports.breaks',
            'reports.attendance.daily',
            'reports.attendance.monthly',
            'reports.attendance.in-out',
        ] as $route) {
            $this->actingAs($employee->user)->get(route($route))->assertForbidden();
        }
    }

    public function test_a_branch_manager_sees_only_their_own_branch(): void
    {
        $manager = $this->makeEmployee(Roles::BRANCH_MANAGER);
        $mine = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $manager->branch]);
        $theirs = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($manager->user)
            ->get(route('reports.attendance.daily', ['date' => '2026-06-10']))
            ->assertOk()
            ->assertSee($mine->full_name)
            ->assertDontSee($theirs->full_name);
    }

    // ------------------------------------------------------------ exports

    public function test_each_report_downloads_the_rows_it_is_showing(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);
        $this->workADay($employee);

        Carbon::setTestNow(Carbon::parse('2026-06-30 19:00:00'));

        $breaks = $this->actingAs($hr->user)
            ->get(route('reports.breaks.export', ['date' => '2026-06-10']));
        $breaks->assertOk();
        $this->assertStringContainsString('Lunch', $breaks->streamedContent());

        $daily = $this->actingAs($hr->user)
            ->get(route('reports.attendance.daily.export', ['date' => '2026-06-10']));
        $daily->assertOk();
        $this->assertStringContainsString($employee->employee_code, $daily->streamedContent());
        $this->assertStringContainsString('09:30', $daily->streamedContent());

        $monthly = $this->actingAs($hr->user)
            ->get(route('reports.attendance.monthly.export', ['month' => 6, 'year' => 2026]));
        $monthly->assertOk();
        $this->assertStringContainsString('Weekly off', $monthly->streamedContent());

        $inOut = $this->actingAs($hr->user)
            ->get(route('reports.attendance.in-out.export', ['month' => 6, 'year' => 2026]));
        $inOut->assertOk();
        $this->assertStringContainsString('10 In', $inOut->streamedContent());
    }
}
