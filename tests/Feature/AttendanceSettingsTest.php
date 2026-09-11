<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Support\FinancialYear;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The three settings that decide how attendance is read: whether people record
 * their own, what an unrecorded day means, and which year the reports run on.
 */
class AttendanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        // Late in a month whose earlier working days have gone unrecorded.
        Carbon::setTestNow(Carbon::parse('2026-06-30 18:00:00'));
        $this->attendance = app(AttendanceService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setting(string $key, mixed $value): void
    {
        $this->seedReferenceData();
        Setting::put($key, $value, 'attendance');
    }

    // ------------------------------------------------- recording your own day

    public function test_an_employee_can_punch_when_self_punching_is_on(): void
    {
        $this->setting('attendance_allow_self_punch', true);
        Setting::put('attendance_require_location', false, 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'))
            ->assertRedirect();

        $this->assertSame(1, Attendance::count());
    }

    public function test_switching_self_punching_off_closes_the_web_form(): void
    {
        $this->setting('attendance_allow_self_punch', false);
        Setting::put('attendance_require_location', false, 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)
            ->post(route('attendance.check-in'))
            ->assertForbidden();

        $this->assertSame(0, Attendance::count());
    }

    public function test_switching_self_punching_off_closes_the_api_too(): void
    {
        $this->setting('attendance_allow_self_punch', false);
        Setting::put('attendance_require_location', false, 'attendance');

        $employee = $this->makeEmployee(Roles::EMPLOYEE);
        $token = $employee->user->createToken('phone')->plainTextToken;

        // The switch lives in the policy, so every way in closes at once.
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->postJson('/api/v1/attendance/check-in')
            ->assertForbidden();
    }

    public function test_the_punch_buttons_are_replaced_by_an_explanation(): void
    {
        $this->setting('attendance_allow_self_punch', false);
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Recording your own attendance is switched off')
            ->assertDontSee('Punch in again');
    }

    public function test_hr_can_still_record_attendance_for_somebody(): void
    {
        $this->setting('attendance_allow_self_punch', false);

        $hr = $this->makeEmployee(Roles::HR_MANAGER);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['branch' => $hr->branch]);

        $this->actingAs($hr->user)->post(route('attendance.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-06-15',
            'check_in' => '09:30',
            'check_out' => '18:30',
            'status' => AttendanceStatus::Present->value,
        ])->assertRedirect();

        $this->assertSame(1, Attendance::count());
    }

    // ---------------------------------------------- a day nobody wrote down

    public function test_an_unmarked_past_day_is_an_absence_when_that_is_the_rule(): void
    {
        $this->setting('attendance_auto_absent', true);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $summary = $this->attendance->monthlySummary($employee, 2026, 6);

        $this->assertGreaterThan(0, $summary['totals']['absent_days']);
        $this->assertSame(0.0, $summary['totals']['unmarked_days']);
        $this->assertSame(AttendanceStatus::Absent, $summary['days']['2026-06-15']['status']);
    }

    public function test_an_unmarked_past_day_reads_as_not_marked_when_it_is_not(): void
    {
        $this->setting('attendance_auto_absent', false);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $summary = $this->attendance->monthlySummary($employee, 2026, 6);

        $this->assertSame(0.0, $summary['totals']['absent_days']);
        $this->assertGreaterThan(0, $summary['totals']['unmarked_days']);
        $this->assertSame(AttendanceStatus::NotMarked, $summary['days']['2026-06-15']['status']);
    }

    public function test_a_day_that_was_recorded_is_untouched_either_way(): void
    {
        $this->setting('attendance_auto_absent', false);
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        $this->attendance->record($employee, [
            'date' => '2026-06-15',
            'check_in' => '09:30',
            'check_out' => '18:30',
        ]);

        $summary = $this->attendance->monthlySummary($employee, 2026, 6);

        $this->assertSame(AttendanceStatus::Present, $summary['days']['2026-06-15']['status']);
        $this->assertSame(1.0, $summary['totals']['present_days']);
    }

    public function test_the_roster_says_not_marked_rather_than_absent(): void
    {
        $this->setting('attendance_auto_absent', false);
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $this->actingAs($hr->user)
            ->get(route('attendance.daily', ['date' => '2026-06-15']))
            ->assertOk()
            ->assertSee('Not marked');
    }

    // ------------------------------------------------------- and the payslip

    /** A month with no attendance at all, run through payroll. */
    protected function payslipFor(bool $autoAbsent): float
    {
        $this->setting('attendance_auto_absent', $autoAbsent);

        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2020-01-01']);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2026-01-01',
            'ctc_annual' => 600000,
            'basic_salary' => 25000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        // One day recorded, the rest of the month left blank.
        $this->attendance->record($employee, [
            'date' => '2026-06-01',
            'check_in' => '09:30',
            'check_out' => '18:30',
        ]);

        $payroll = app(PayrollService::class)->createRun([
            'company_id' => $this->defaultCompany()->id,
            'month' => 6,
            'year' => 2026,
        ]);

        app(PayrollService::class)->generate($payroll);

        return (float) $payroll->fresh()->payslips()->first()->lop_days;
    }

    public function test_unmarked_days_cost_pay_when_they_count_as_absence(): void
    {
        $this->assertGreaterThan(0, $this->payslipFor(autoAbsent: true));
    }

    public function test_unmarked_days_cost_nothing_when_they_do_not(): void
    {
        // The whole point of the switch: a forgotten punch must not quietly
        // cut somebody's salary.
        $this->assertSame(0.0, $this->payslipFor(autoAbsent: false));
    }

    // ------------------------------------------------------ the business year

    public function test_the_financial_year_runs_from_the_month_that_is_set(): void
    {
        $this->setting('financial_year_start_month', 4);

        $this->assertSame(2026, FinancialYear::of(Carbon::parse('2026-04-01')));
        $this->assertSame(2026, FinancialYear::of(Carbon::parse('2027-03-31')));
        $this->assertSame(2025, FinancialYear::of(Carbon::parse('2026-03-31')));

        $this->assertSame('2026-04-01', FinancialYear::start(2026)->toDateString());
        $this->assertSame('2027-03-31', FinancialYear::end(2026)->toDateString());
        $this->assertSame('FY 2026–27', FinancialYear::label(2026));
    }

    public function test_january_makes_it_the_calendar_year_again(): void
    {
        $this->setting('financial_year_start_month', 1);

        $this->assertTrue(FinancialYear::isCalendar());
        $this->assertSame(2026, FinancialYear::of(Carbon::parse('2026-03-31')));
        $this->assertSame('2026', FinancialYear::label(2026));
        $this->assertSame('2026-12-31', FinancialYear::end(2026)->toDateString());
    }

    public function test_the_payroll_report_runs_on_the_business_year(): void
    {
        $this->setting('financial_year_start_month', 4);
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('reports.payroll'))
            ->assertOk()
            // April first, March last, and the year said out loud.
            ->assertSee('FY 2026–27')
            ->assertSee('Apr 2026 to Mar 2027');
    }

    public function test_the_months_of_a_financial_year_start_in_april(): void
    {
        $this->setting('financial_year_start_month', 4);

        $months = FinancialYear::months(2026);

        $this->assertCount(12, $months);
        $this->assertSame('2026-04-01', $months[0]->toDateString());
        $this->assertSame('2027-03-01', $months[11]->toDateString());
    }

    public function test_joiners_and_leavers_are_counted_for_the_business_year(): void
    {
        $this->setting('financial_year_start_month', 4);
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        // February 2026 falls in the year that ended on 31 March, so on a
        // calendar year both of these would count and on this one only May does.
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2026-02-10']);
        $this->makeEmployee(Roles::EMPLOYEE, ['date_of_joining' => '2026-05-10']);

        $response = $this->actingAs($admin->user)->get(route('reports.employees'))->assertOk();

        $this->assertSame(1, $response->viewData('newJoiners'));
        $this->assertSame('FY 2026–27', $response->viewData('yearLabel'));
    }
}
