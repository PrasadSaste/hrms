<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\OvertimeService;
use App\Services\PayrollService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payroll;

    protected AttendanceService $attendance;

    protected OvertimeService $overtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
        $this->attendance = app(AttendanceService::class);
        $this->overtime = app(OvertimeService::class);
        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Turn overtime on, after the employee exists: creating one seeds the
     * settings table, which would put the switch back where it started.
     */
    protected function enableOvertime(array $settings = []): void
    {
        Setting::put('payroll_overtime_enabled', true, 'payroll');

        foreach ($settings as $key => $value) {
            Setting::put($key, $value, 'payroll');
        }
    }

    /** 12 lakh CTC: 100,000 a month, basic 40,000. */
    protected function employeeWithSalary(array $attributes = []): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2023-01-01'),
        ], $attributes));

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2023-01-01',
            'ctc_annual' => 1200000,
            'basic_salary' => 40000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        $this->payroll->applyDefaultComponents($structure);

        return $employee->fresh();
    }

    /**
     * Present on every working day of June 2026, with `$extraHours` of overtime
     * spread over the first working days of the month.
     */
    protected function markAttendance(Employee $employee, float $extraHours = 0.0): void
    {
        $cursor = Carbon::create(2026, 6, 1);
        $end = $cursor->copy()->endOfMonth();
        $remaining = $extraHours;

        while ($cursor->lte($end)) {
            if ($cursor->isoWeekday() <= 5) {
                // A nine-hour day is eight worked plus an hour of break, so a
                // plain day carries no overtime and each extra hour shows up.
                $extra = min(1.0, $remaining);
                $remaining -= $extra;

                $this->attendance->record($employee, [
                    'date' => $cursor->toDateString(),
                    'check_in' => '09:30',
                    'check_out' => Carbon::parse('18:30')->addMinutes((int) round($extra * 60))->format('H:i'),
                ]);
            }
            $cursor->addDay();
        }
    }

    protected function runPayrollFor(Employee $employee): Payslip
    {
        $run = $this->payroll->createRun(['month' => 6, 'year' => 2026]);
        $this->payroll->generate($run);

        return Payslip::where('payroll_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
    }

    public function test_overtime_is_off_by_default(): void
    {
        $this->seedReferenceData();

        $this->assertFalse($this->overtime->enabled());
    }

    public function test_hours_are_recorded_but_not_paid_while_it_is_off(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 6);

        $payslip = $this->runPayrollFor($employee);

        $this->assertGreaterThan(0, $payslip->overtime_hours);
        $this->assertSame(0.0, $payslip->overtime_amount);
        $this->assertSame(0.0, $payslip->overtime_rate);
        $this->assertNull($payslip->items->firstWhere('code', 'OT'));
    }

    public function test_switching_it_on_pays_the_recorded_hours(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 6);
        $this->enableOvertime();

        $payslip = $this->runPayrollFor($employee);

        // June 2026 has 22 working days. Basic 40,000 over 22 × 8 hours is
        // 227.27 an hour; doubled, 454.54. Six hours of it is 2,727.24.
        $this->assertSame(6.0, $payslip->overtime_hours);
        $this->assertSame(454.54, $payslip->overtime_rate);
        $this->assertSame(2727.24, $payslip->overtime_amount);

        $line = $payslip->items->firstWhere('code', 'OT');
        $this->assertNotNull($line);
        $this->assertSame(2727.24, $line->amount);
        $this->assertSame('Overtime (6.00 hrs)', $line->name);
        $this->assertNull($line->salary_component_id);
    }

    public function test_overtime_is_part_of_gross_so_deductions_on_gross_see_it(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 6);
        $without = $this->runPayrollFor($employee);
        $plainGross = $without->gross_earnings;

        $without->payroll->delete();

        $this->enableOvertime();
        $with = $this->runPayrollFor($employee->fresh());

        $this->assertSame(round($plainGross + 2727.24, 2), $with->gross_earnings);
    }

    public function test_the_multiplier_and_the_basis_are_settings(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 4);
        $this->enableOvertime([
            'payroll_overtime_multiplier' => '1.5',
            'payroll_overtime_hours_per_day' => '9',
        ]);

        $payslip = $this->runPayrollFor($employee);

        // 40,000 over 22 × 9 hours is 202.02; one and a half times, 303.03.
        $this->assertSame(303.03, $payslip->overtime_rate);
        $this->assertSame(1212.12, $payslip->overtime_amount);
    }

    public function test_the_gross_basis_pays_more_than_the_basic_one(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 4);
        $this->enableOvertime(['payroll_overtime_basis' => 'gross']);

        $payslip = $this->runPayrollFor($employee);

        $this->assertGreaterThan(454.54, $payslip->overtime_rate);
    }

    public function test_hours_beyond_the_monthly_cap_are_reported_but_not_paid(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 6);
        $this->enableOvertime(['payroll_overtime_monthly_cap_hours' => '4']);

        $payslip = $this->runPayrollFor($employee);

        $this->assertSame(6.0, $payslip->overtime_hours, 'The hours worked are still on the slip.');
        $this->assertSame(round(454.54 * 4, 2), $payslip->overtime_amount);
        $this->assertSame('Overtime (4.00 hrs)', $payslip->items->firstWhere('code', 'OT')->name);
    }

    public function test_an_employee_can_be_left_out_of_overtime(): void
    {
        $employee = $this->employeeWithSalary(['overtime_eligible' => false]);
        $this->markAttendance($employee, 6);
        $this->enableOvertime();

        $payslip = $this->runPayrollFor($employee);

        $this->assertSame(6.0, $payslip->overtime_hours);
        $this->assertSame(0.0, $payslip->overtime_amount);
    }

    public function test_a_month_with_no_extra_hours_gets_no_overtime_line(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee);
        $this->enableOvertime();

        $payslip = $this->runPayrollFor($employee);

        $this->assertSame(0.0, $payslip->overtime_amount);
        $this->assertNull($payslip->items->firstWhere('code', 'OT'));
    }

    public function test_the_slip_and_its_pdf_carry_the_rate_that_was_paid(): void
    {
        $employee = $this->employeeWithSalary();
        $this->markAttendance($employee, 6);
        $this->enableOvertime();
        $payslip = $this->runPayrollFor($employee);

        $payslip->payroll->update(['status' => \App\Enums\PayrollStatus::Approved]);
        $payslip->update(['status' => 'published']);

        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('payslips.show', $payslip))
            ->assertOk()
            ->assertSee('Overtime (6.00 hrs)')
            ->assertSee('454.54');

        $pdf = app(\App\Services\PayslipPdfService::class)->bytes($payslip);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_an_administrator_can_change_the_overtime_settings(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Pay for overtime');

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->settingsPayload([
                'payroll_overtime_enabled' => '1',
                'payroll_overtime_basis' => 'gross',
                'payroll_overtime_multiplier' => '2.5',
            ]))
            ->assertRedirect();

        $this->assertTrue($this->overtime->enabled());
        $this->assertSame('gross', $this->overtime->basis());
        $this->assertSame(2.5, $this->overtime->multiplier());
    }

    public function test_a_nonsense_multiplier_is_refused(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->settingsPayload([
                'payroll_overtime_multiplier' => '99',
            ]))
            ->assertSessionHasErrors('payroll_overtime_multiplier');
    }

    /** The settings form posts everything at once; these are its required parts. */
    protected function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Beyond Sure',
            'brand_color' => '#2563eb',
            'brand_secondary_color' => '#0f172a',
            'brand_tertiary_color' => '#0d9488',
            'theme_surface' => \App\Services\BrandPalette::SURFACES[0],
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd M Y',
            'employee_code_prefix' => 'EMP',
            'employee_code_padding' => '4',
            'financial_year_start_month' => '4',
        ], $overrides);
    }
}
