<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\Setting;
use App\Models\TaxDeclaration;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Services\TaxDeclarationService;
use App\Services\TaxWithholdingService;
use App\Support\FinancialYear;
use App\Support\Roles;
use App\Support\TaxRegimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaxWithholdingTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payroll;

    protected TaxWithholdingService $withholding;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payroll = app(PayrollService::class);
        $this->withholding = app(TaxWithholdingService::class);
        Carbon::setTestNow(Carbon::parse('2026-07-05 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Turn deduction on.
     *
     * After the employee exists, because creating one seeds the settings and
     * would put the switch back where it started.
     */
    protected function enableTds(): void
    {
        Setting::put('payroll_tds_enabled', true, 'payroll');
    }

    protected function employeeOnSalary(float $ctc = 2400000): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => Carbon::parse('2020-01-01'),
            'date_of_birth' => Carbon::parse('1990-01-01'),
        ]);

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2020-01-01',
            'ctc_annual' => $ctc,
            'basic_salary' => round($ctc / 12 * 0.4, 2),
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        $this->payroll->applyDefaultComponents($structure);
        $this->markFullAttendance($employee, 2026, 6);

        return $employee->fresh();
    }

    protected function markFullAttendance(Employee $employee, int $year, int $month): void
    {
        $attendance = app(AttendanceService::class);
        $cursor = Carbon::create($year, $month, 1);
        $end = $cursor->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            if ($cursor->isoWeekday() <= 5) {
                $attendance->record($employee, [
                    'date' => $cursor->toDateString(),
                    'check_in' => '09:30',
                    'check_out' => '18:30',
                ]);
            }
            $cursor->addDay();
        }
    }

    protected function runFor(Employee $employee, int $month = 6): Payslip
    {
        $run = $this->payroll->createRun(['month' => $month, 'year' => 2026]);
        $this->payroll->generate($run);

        return Payslip::where('payroll_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
    }

    public function test_it_is_off_by_default(): void
    {
        $this->seedReferenceData();

        $this->assertFalse($this->withholding->enabled());
    }

    public function test_nothing_is_computed_while_it_is_off(): void
    {
        $employee = $this->employeeOnSalary();
        $payslip = $this->runFor($employee);

        $this->assertSame(0.0, $payslip->tax_deducted);
        $this->assertNull($payslip->tax_regime);

        // The structure's own flat-percentage TDS component is untouched: an
        // installation that has not switched this on behaves exactly as it
        // did before any of this existed.
        $line = $payslip->items->firstWhere('code', 'TDS');
        $this->assertNotNull($line);
        $this->assertNotNull($line->salary_component_id, 'It is the component’s line, not a computed one.');
    }

    public function test_switching_it_on_replaces_the_flat_percentage_component(): void
    {
        $employee = $this->employeeOnSalary();
        $flat = $this->runFor($employee)->items->firstWhere('code', 'TDS');
        $flat->payslip->payroll->delete();

        $this->enableTds();
        $computed = $this->runFor($employee->fresh())->items->where('code', 'TDS');

        $this->assertCount(1, $computed, 'One tax line, never two.');
        $this->assertNull($computed->first()->salary_component_id);
        $this->assertNotSame($flat->amount, $computed->first()->amount);
    }

    public function test_switching_it_on_adds_a_tax_line_to_the_slip(): void
    {
        $employee = $this->employeeOnSalary();
        $this->enableTds();

        $payslip = $this->runFor($employee);

        $this->assertGreaterThan(0, $payslip->tax_deducted);
        $this->assertSame(TaxRegimes::default(), $payslip->tax_regime);

        $line = $payslip->items->firstWhere('code', 'TDS');
        $this->assertNotNull($line);
        $this->assertSame($payslip->tax_deducted, $line->amount);
        $this->assertTrue($line->is_statutory);
        $this->assertNull($line->salary_component_id);
    }

    public function test_the_tax_line_comes_off_net_pay_and_not_off_gross(): void
    {
        $employee = $this->employeeOnSalary();
        $without = $this->runFor($employee);
        $grossWithout = $without->gross_earnings;
        $deductionsWithout = $without->total_deductions;
        $flatTds = $without->items->firstWhere('code', 'TDS')->amount;

        $without->payroll->delete();
        $this->enableTds();
        $with = $this->runFor($employee->fresh());

        $this->assertSame($grossWithout, $with->gross_earnings, 'Gross is untouched.');

        // The computed figure stands in place of the flat component's, so the
        // difference in deductions is the difference between those two and
        // nothing else moves.
        $this->assertSame(
            round($deductionsWithout - $flatTds + $with->tax_deducted, 2),
            $with->total_deductions,
        );
        $this->assertSame(
            round($with->gross_earnings - $with->total_deductions, 2),
            $with->net_pay,
        );
    }

    public function test_the_deduction_is_whole_rupees(): void
    {
        $employee = $this->employeeOnSalary();
        $this->enableTds();

        $payslip = $this->runFor($employee);

        $this->assertSame((float) round($payslip->tax_deducted), $payslip->tax_deducted);
    }

    public function test_declaring_investments_lowers_what_is_taken(): void
    {
        $employee = $this->employeeOnSalary();
        $this->enableTds();

        $service = app(TaxDeclarationService::class);
        $year = FinancialYear::of(Carbon::parse('2026-06-30'));
        $declaration = $service->forEmployee($employee, $year);

        // Compared within one regime. Declaring *and* switching regime at the
        // same time proves nothing: on this salary the new regime is cheaper
        // even with three and a half lakh of deductions given up.
        $service->save($declaration, [], ['regime' => TaxRegimes::OLD, 'metro' => false]);
        $bare = $this->runFor($employee);
        $taxBefore = $bare->tax_deducted;
        $bare->payroll->delete();

        $service->save($declaration->fresh('items'), ['80c' => 150000, 'section24b' => 200000], [
            'regime' => TaxRegimes::OLD,
            'metro' => false,
        ]);

        $after = $this->runFor($employee->fresh());

        $this->assertLessThan($taxBefore, $after->tax_deducted);
        $this->assertSame(TaxRegimes::OLD, $after->tax_regime);
    }

    public function test_the_regime_an_employee_chose_is_the_one_used(): void
    {
        $employee = $this->employeeOnSalary();
        $this->enableTds();

        $service = app(TaxDeclarationService::class);
        $year = FinancialYear::of(Carbon::parse('2026-06-30'));
        $service->save(
            $service->forEmployee($employee, $year),
            [],
            ['regime' => TaxRegimes::OLD, 'metro' => false],
        );

        $this->assertSame(TaxRegimes::OLD, $this->runFor($employee->fresh())->tax_regime);
    }

    public function test_what_has_already_been_taken_is_not_taken_again(): void
    {
        $employee = $this->employeeOnSalary();
        $this->enableTds();

        $june = $this->runFor($employee);
        $this->assertGreaterThan(0, $june->tax_deducted);

        // The next month's projection sees June's deduction and spreads what
        // is left over one fewer month, so the two are close but not equal.
        $this->markFullAttendance($employee, 2026, 7);
        $july = $this->runFor($employee->fresh(), month: 7);

        $this->assertGreaterThan(0, $july->tax_deducted);
        $this->assertEqualsWithDelta($june->tax_deducted, $july->tax_deducted, 1500);
    }

    public function test_somebody_who_owes_nothing_has_nothing_taken(): void
    {
        // Well under the point at which any tax is due. The flat five per cent
        // the component would have charged must not survive as a fallback:
        // switching the feature on for a low earner has to leave them alone.
        $employee = $this->employeeOnSalary(ctc: 300000);
        $this->enableTds();

        $payslip = $this->runFor($employee);

        $this->assertSame(0.0, $payslip->tax_deducted);
        $this->assertNull($payslip->items->firstWhere('code', 'TDS'));
    }

    public function test_an_administrator_can_switch_it_on_from_the_settings_screen(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Deduct income tax from salaries');

        $this->actingAs($admin->user)
            ->put(route('settings.update'), [
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
                'payroll_tds_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($this->withholding->enabled());
    }
}
