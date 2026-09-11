<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\TaxDeclaration;
use App\Services\IncomeTaxService;
use App\Services\PayrollService;
use App\Services\TaxDeclarationService;
use App\Support\FinancialYear;
use App\Support\TaxRegimes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The arithmetic, checked at the boundaries rather than in the middle.
 *
 * Every expected figure below is worked out by hand in the comment beside it.
 * A test that only asserts the code agrees with itself would have passed on
 * every version of this that was wrong.
 */
class IncomeTaxTest extends TestCase
{
    use RefreshDatabase;

    protected IncomeTaxService $tax;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tax = app(IncomeTaxService::class);
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function taxOn(float $income, string $regime, int $year = 2025): array
    {
        return $this->tax->taxOn($income, TaxRegimes::rules($year, $regime));
    }

    public function test_the_new_regime_slabs_add_up(): void
    {
        // Nil to 4L; 5% of the next 4L is 20,000; 10% of the next 4L is
        // 40,000; 15% of the next 4L is 60,000. At 16L that is 1,20,000,
        // plus 4% cess of 4,800.
        $tax = $this->taxOn(1600000, TaxRegimes::NEW);

        $this->assertSame(120000.0, $tax['before_rebate']);
        $this->assertSame(4800.0, $tax['cess']);
        $this->assertSame(124800.0, $tax['total']);
    }

    public function test_the_old_regime_slabs_add_up(): void
    {
        // 5% of 2.5L is 12,500; 20% of the next 5L is 1,00,000; 30% of the
        // last 5L is 1,50,000. 2,62,500 plus 10,500 of cess.
        $tax = $this->taxOn(1500000, TaxRegimes::OLD);

        $this->assertSame(262500.0, $tax['before_rebate']);
        $this->assertSame(273000.0, $tax['total']);
    }

    public function test_the_rebate_wipes_out_the_tax_up_to_its_ceiling(): void
    {
        $tax = $this->taxOn(1200000, TaxRegimes::NEW);

        $this->assertSame(60000.0, $tax['before_rebate']);
        $this->assertSame(60000.0, $tax['rebate']);
        $this->assertSame(0.0, $tax['total'], 'Nothing is due at exactly the rebate ceiling.');
    }

    public function test_marginal_relief_stops_the_cliff_just_over_the_ceiling(): void
    {
        // A hundred rupees over the ceiling. Without relief the whole 60,015
        // would fall due; the relief holds the extra tax to the extra income,
        // so a hundred rupees of tax and four of cess.
        $tax = $this->taxOn(1200100, TaxRegimes::NEW);

        $this->assertSame(60015.0, $tax['before_rebate']);
        $this->assertSame(59915.0, $tax['rebate']);
        $this->assertSame(104.0, $tax['total']);
    }

    public function test_the_rebate_runs_out_where_the_extra_income_exceeds_the_tax(): void
    {
        // 75,000 over the ceiling, and the tax of 71,250 is less than that,
        // so no relief is due at all and the whole amount stands.
        $tax = $this->taxOn(1275000, TaxRegimes::NEW);

        $this->assertSame(0.0, $tax['rebate']);
        $this->assertSame(74100.0, $tax['total']);
    }

    public function test_the_old_regime_rebate_has_its_own_ceiling(): void
    {
        $this->assertSame(0.0, $this->taxOn(500000, TaxRegimes::OLD)['total']);
        $this->assertGreaterThan(0, $this->taxOn(500100, TaxRegimes::OLD)['total']);
    }

    public function test_no_surcharge_at_exactly_fifty_lakh(): void
    {
        $tax = $this->taxOn(5000000, TaxRegimes::NEW);

        $this->assertSame(0.0, $tax['surcharge']);
        // 3,00,000 to 24L, then 30% of the remaining 26L.
        $this->assertSame(1080000.0, $tax['before_rebate']);
    }

    public function test_surcharge_marginal_relief_holds_the_increase_to_the_income(): void
    {
        // At 51L: tax 11,10,000, surcharge at 10% would be 1,11,000. Somebody
        // at exactly 50L pays 10,80,000 and no surcharge, so the total here
        // cannot exceed 10,80,000 + the 1,00,000 that took them over.
        $tax = $this->taxOn(5100000, TaxRegimes::NEW);

        $this->assertSame(0.10, $tax['surcharge_rate']);
        $this->assertSame(41000.0, $tax['surcharge_relief']);
        $this->assertSame(70000.0, $tax['surcharge']);
        $this->assertSame(1180000.0, round($tax['after_rebate'] + $tax['surcharge'], 2));
        $this->assertSame(1227200.0, $tax['total'], 'The capped total plus 4% cess.');
    }

    public function test_the_new_regime_surcharge_stops_at_a_quarter(): void
    {
        $newRules = TaxRegimes::rules(2025, TaxRegimes::NEW);
        $oldRules = TaxRegimes::rules(2025, TaxRegimes::OLD);

        $this->assertSame(0.25, collect($newRules['surcharge'])->last()['rate']);
        $this->assertSame(0.37, collect($oldRules['surcharge'])->last()['rate']);
    }

    public function test_age_widens_the_first_band_under_the_old_regime_only(): void
    {
        $senior = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_birth' => Carbon::parse('1960-01-01'),
        ]);

        $old = $this->tax->taxOn(600000, TaxRegimes::rules(2025, TaxRegimes::OLD), $senior, 2025);
        $young = $this->taxOn(600000, TaxRegimes::OLD);

        // Nil to 3L rather than 2.5L, so 5% is charged on 2L not 2.5L —
        // 2,500 less tax before cess.
        $this->assertSame(2500.0, round($young['before_rebate'] - $old['before_rebate'], 2));

        $new = $this->tax->taxOn(600000, TaxRegimes::rules(2025, TaxRegimes::NEW), $senior, 2025);
        $this->assertSame($this->taxOn(600000, TaxRegimes::NEW)['before_rebate'], $new['before_rebate']);
    }

    public function test_a_year_the_catalogue_does_not_know_falls_back_and_says_so(): void
    {
        $rules = TaxRegimes::rules(2099, TaxRegimes::NEW);

        $this->assertTrue($rules['assumed']);
        $this->assertSame(2025, $rules['rules_year']);

        $known = TaxRegimes::rules(2025, TaxRegimes::NEW);
        $this->assertFalse($known['assumed']);
    }

    // ------------------------------------------------------------ deductions

    /** 12 lakh a year, basic 40,000 a month, HRA 20,000 a month. */
    protected function employeeOnSalary(array $attributes = [], float $ctc = 1200000): Employee
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, array_merge([
            'date_of_joining' => Carbon::parse('2020-01-01'),
            'date_of_birth' => Carbon::parse('1990-01-01'),
        ], $attributes));

        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2020-01-01',
            'ctc_annual' => $ctc,
            'basic_salary' => round($ctc / 12 * 0.4, 2),
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        app(PayrollService::class)->applyDefaultComponents($structure);

        return $employee->fresh();
    }

    protected function declare(Employee $employee, array $amounts, array $attributes = []): TaxDeclaration
    {
        $service = app(TaxDeclarationService::class);
        $declaration = $service->forEmployee($employee, FinancialYear::current());

        return $service->save($declaration, $amounts, array_merge([
            'regime' => TaxRegimes::OLD,
            'metro' => false,
        ], $attributes));
    }

    public function test_a_section_is_capped_at_its_ceiling_and_says_so(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declare($employee, ['80c' => 250000]);

        $result = $this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD);
        $line = collect($result['exemptions']['lines'])->firstWhere('section', '80c');

        $this->assertSame(250000.0, $line['declared']);
        $this->assertSame(150000.0, $line['allowed']);
        $this->assertStringContainsString('ceiling', $line['reason']);
    }

    public function test_the_sections_that_share_a_ceiling_share_it(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declare($employee, ['80c' => 150000, '80ccd1' => 50000]);

        $result = $this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD);
        $lines = collect($result['exemptions']['lines'])->keyBy('section');

        $this->assertSame(150000.0, $lines['80c']['allowed']);
        $this->assertSame(0.0, $lines['80ccd1']['allowed'], 'The pot is already empty.');
        $this->assertStringContainsString('combined ceiling', $lines['80ccd1']['reason']);
    }

    public function test_the_additional_pension_sits_outside_that_ceiling(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declare($employee, ['80c' => 150000, '80ccd1b' => 50000]);

        $lines = collect($this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD)['exemptions']['lines'])
            ->keyBy('section');

        $this->assertSame(150000.0, $lines['80c']['allowed']);
        $this->assertSame(50000.0, $lines['80ccd1b']['allowed']);
    }

    public function test_nothing_is_allowed_under_the_new_regime_but_it_is_still_shown(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declare($employee, ['80c' => 150000], ['regime' => TaxRegimes::NEW]);

        $result = $this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::NEW);
        $line = collect($result['exemptions']['lines'])->firstWhere('section', '80c');

        $this->assertSame(150000.0, $line['declared'], 'What they claimed is still visible.');
        $this->assertSame(0.0, $line['allowed']);
        $this->assertFalse($line['applies']);
        $this->assertSame(0.0, $result['exemptions']['total']);
    }

    public function test_house_rent_relief_is_the_least_of_three_figures(): void
    {
        $employee = $this->employeeOnSalary();

        // Basic 40,000 a month is 4,80,000 a year; HRA is half of basic, so
        // 2,40,000 a year. Two fifths of salary is 1,92,000. Rent of 3,00,000
        // less a tenth of salary (48,000) is 2,52,000. The least is 1,92,000.
        $this->declare($employee, ['hra' => 300000]);

        $line = collect($this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD)['exemptions']['lines'])
            ->firstWhere('section', 'hra');

        $this->assertSame(192000.0, $line['allowed']);
    }

    public function test_living_in_a_metro_allows_half_of_salary_rather_than_two_fifths(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declare($employee, ['hra' => 300000], ['metro' => true]);

        $line = collect($this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD)['exemptions']['lines'])
            ->firstWhere('section', 'hra');

        // Half of 4,80,000 is 2,40,000, which now ties with the allowance
        // itself; the rent figure of 2,52,000 is still the largest.
        $this->assertSame(240000.0, $line['allowed']);
    }

    public function test_rent_paid_with_no_allowance_is_exempt_at_nothing(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'date_of_joining' => Carbon::parse('2020-01-01'),
        ]);

        // A structure with a basic and no house rent allowance at all.
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_from' => '2020-01-01',
            'ctc_annual' => 1200000,
            'basic_salary' => 40000,
            'currency' => 'INR',
            'payment_mode' => 'bank_transfer',
            'status' => 'active',
        ]);

        $this->declare($employee, ['hra' => 300000]);

        $line = collect($this->tax->computeFor($employee->fresh(), FinancialYear::current(), TaxRegimes::OLD)['exemptions']['lines'])
            ->firstWhere('section', 'hra');

        $this->assertSame(0.0, $line['allowed']);
        $this->assertStringContainsString('No house rent allowance is paid', $line['reason']);
    }

    public function test_the_verified_figure_governs_once_hr_has_set_one(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declare($employee, ['80c' => 150000]);
        $verifier = $this->makeEmployee(Roles::HR_MANAGER);

        app(TaxDeclarationService::class)->verify($declaration, ['80c' => 90000], $verifier->user);

        $line = collect($this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD)['exemptions']['lines'])
            ->firstWhere('section', '80c');

        $this->assertSame(90000.0, $line['declared'], 'The effective figure is what counts from here.');
        $this->assertSame(90000.0, $line['allowed']);
    }

    public function test_an_unruled_item_still_counts_at_what_was_declared(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declare($employee, ['80c' => 150000, '80e' => 40000]);
        $verifier = $this->makeEmployee(Roles::HR_MANAGER);

        // A figure for one section only; the other is left blank, which is
        // not the same as being cut to nothing.
        app(TaxDeclarationService::class)->verify($declaration, ['80c' => 90000], $verifier->user);

        $lines = collect($this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD)['exemptions']['lines'])
            ->keyBy('section');

        $this->assertSame(90000.0, $lines['80c']['allowed']);
        $this->assertSame(40000.0, $lines['80e']['allowed']);
    }

    public function test_accepting_nothing_is_a_nought_and_is_recorded_as_one(): void
    {
        $employee = $this->employeeOnSalary();
        $declaration = $this->declare($employee, ['80c' => 150000]);
        $verifier = $this->makeEmployee(Roles::HR_MANAGER);

        app(TaxDeclarationService::class)->verify($declaration, ['80c' => 0], $verifier->user);

        $item = $declaration->fresh('items')->items->firstWhere('section', '80c');

        $this->assertSame(0.0, $item->verified_amount);
        $this->assertTrue($item->isVerified());
        $this->assertSame(150000.0, $item->declared_amount, 'What was claimed is not overwritten.');
    }

    public function test_the_standard_deduction_applies_under_both_regimes(): void
    {
        $employee = $this->employeeOnSalary();

        $new = $this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::NEW);
        $old = $this->tax->computeFor($employee, FinancialYear::current(), TaxRegimes::OLD);

        $this->assertSame(75000.0, $new['standard_deduction']);
        $this->assertSame(50000.0, $old['standard_deduction']);
    }

    public function test_both_regimes_are_worked_out_and_neither_is_chosen(): void
    {
        $employee = $this->employeeOnSalary();
        $this->declare($employee, ['80c' => 150000, 'section24b' => 200000]);

        $comparison = $this->tax->compare($employee, FinancialYear::current());

        $this->assertCount(2, $comparison['regimes']);
        $this->assertContains($comparison['cheaper'], [TaxRegimes::NEW, TaxRegimes::OLD]);
        $this->assertSame(
            round(
                max(array_map(fn ($r) => $r['tax']['total'], $comparison['regimes']))
                - min(array_map(fn ($r) => $r['tax']['total'], $comparison['regimes'])),
                2,
            ),
            $comparison['saving'],
        );
    }

    public function test_the_months_left_in_the_year_count_the_one_being_run(): void
    {
        // April, the first month of the year: all twelve are still to come.
        $this->assertSame(12, $this->tax->monthsRemaining(2026, Carbon::parse('2026-04-15')));
        $this->assertSame(1, $this->tax->monthsRemaining(2026, Carbon::parse('2027-03-31')));
        $this->assertSame(7, $this->tax->monthsRemaining(2026, Carbon::parse('2026-09-10')));
        $this->assertSame(0, $this->tax->monthsRemaining(2026, Carbon::parse('2027-04-01')));
    }

    public function test_a_leaver_is_only_projected_to_their_last_month(): void
    {
        $employee = $this->employeeOnSalary(['date_of_exit' => Carbon::parse('2026-08-31')]);

        $this->assertSame(
            5,
            $this->tax->monthsRemaining(2026, Carbon::parse('2026-04-15'), $employee),
            'April to August, not April to March.',
        );
    }
}
