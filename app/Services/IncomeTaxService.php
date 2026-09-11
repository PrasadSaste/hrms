<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Models\TaxDeclaration;
use App\Support\FinancialYear;
use App\Support\TaxDeductionSections;
use App\Support\TaxRegimes;
use Illuminate\Support\Carbon;

/**
 * What an employee will owe for the year, and what to take this month.
 *
 * Tax deducted at source is not a percentage of a payslip. It is a projection:
 * the whole year's income is estimated, the year's tax on it worked out, what
 * has already been deducted taken off, and what is left spread across the
 * months that remain. That is why a rise in October changes every remaining
 * payslip and not just the ones after it, and why the figure moves when
 * somebody declares an investment in January.
 *
 * **Everything returned here is explainable.** `computeFor()` hands back not a
 * number but the working — gross, each exemption, taxable income, the tax at
 * each slab, surcharge, rebate, cess, what has been paid, what is left. The
 * computation sheet renders that array directly, because "your tax is
 * ₹1,04,000" is not checkable and is exactly what gets queried.
 *
 * Nothing here chooses a regime. Both are computed, the difference is shown,
 * and the employee decides — it is their choice to make and, once made, the
 * declaration records it.
 */
class IncomeTaxService
{
    /** House rent allowance is exempt at the least of three figures. */
    public const HRA_METRO_SHARE = 0.50;

    public const HRA_NON_METRO_SHARE = 0.40;

    /** Rent above a tenth of salary is what the third figure measures. */
    public const HRA_RENT_THRESHOLD_SHARE = 0.10;

    public function __construct(protected PayrollService $payroll) {}

    /**
     * The whole computation for one employee and year, under one regime.
     *
     * @return array<string, mixed>
     */
    public function computeFor(
        Employee $employee,
        int $financialYear,
        ?string $regime = null,
        ?Carbon $asOf = null,
    ): array {
        $declaration = $this->declarationFor($employee, $financialYear);
        $regime ??= $declaration?->regime ?? TaxRegimes::default();
        $rules = TaxRegimes::rules($financialYear, $regime);

        $salary = $this->projectedSalary($employee, $financialYear, $asOf);
        $additions = $this->additions($declaration, $regime);
        $exemptions = $this->exemptions($employee, $declaration, $regime, $salary['annual_basic']);

        $grossTotal = round($salary['projected_gross'] + $additions['total'], 2);

        // The standard deduction comes off salary income and survives both
        // regimes; it is not something anybody declares.
        $standardDeduction = min((float) $rules['standard_deduction'], max(0.0, $salary['projected_gross']));

        $taxableIncome = round(max(0.0, $grossTotal - $standardDeduction - $exemptions['total']), 2);

        $tax = $this->taxOn($taxableIncome, $rules, $employee, $financialYear);

        $alreadyDeducted = $this->alreadyDeducted($employee, $financialYear);
        $remaining = round(max(0.0, $tax['total'] - $alreadyDeducted), 2);
        $monthsLeft = $this->monthsRemaining($financialYear, $asOf);

        return [
            'employee' => $employee,
            'financial_year' => $financialYear,
            'year_label' => FinancialYear::label($financialYear),
            'regime' => $regime,
            'regime_label' => TaxRegimes::label($regime),
            // Says out loud when the arithmetic ran on an older Finance Act
            // than the year asked about, rather than looking authoritative.
            'rules_year' => $rules['rules_year'],
            'rules_assumed' => $rules['assumed'],

            'salary' => $salary,
            'additions' => $additions,
            'exemptions' => $exemptions,

            'gross_total' => $grossTotal,
            'standard_deduction' => round($standardDeduction, 2),
            'taxable_income' => $taxableIncome,

            'tax' => $tax,
            'already_deducted' => $alreadyDeducted,
            'remaining' => $remaining,
            'months_remaining' => $monthsLeft,
            'monthly' => $monthsLeft > 0 ? round($remaining / $monthsLeft, 2) : $remaining,
        ];
    }

    /**
     * Both regimes side by side, and which one costs less.
     *
     * @return array<string, mixed>
     */
    public function compare(Employee $employee, int $financialYear, ?Carbon $asOf = null): array
    {
        $results = [];

        foreach (array_keys(TaxRegimes::labels()) as $regime) {
            $results[$regime] = $this->computeFor($employee, $financialYear, $regime, $asOf);
        }

        $cheaper = collect($results)->sortBy(fn ($r) => $r['tax']['total'])->keys()->first();

        return [
            'regimes' => $results,
            'cheaper' => $cheaper,
            'saving' => round(
                max(array_map(fn ($r) => $r['tax']['total'], $results))
                - min(array_map(fn ($r) => $r['tax']['total'], $results)),
                2,
            ),
        ];
    }

    /**
     * The year's salary: what has been paid, plus what is expected.
     *
     * Months already run are taken from the payslips themselves rather than
     * from the structure, so a month with loss of pay in it projects the year
     * honestly instead of assuming everybody is paid in full for ever.
     *
     * @return array<string, mixed>
     */
    public function projectedSalary(Employee $employee, int $financialYear, ?Carbon $asOf = null): array
    {
        $start = FinancialYear::start($financialYear);
        $end = FinancialYear::end($financialYear);
        $asOf = $asOf ? $asOf->copy() : Carbon::today();

        $slips = Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('period_start', [$start, $end])
            ->orderBy('period_start')
            ->get();

        $paidGross = round($slips->sum('gross_earnings'), 2);
        $monthsPaid = $slips->count();

        $structure = $this->structureFor($employee, $asOf);
        $monthlyGross = $structure ? $this->payroll->monthlyGross($structure) : 0.0;
        $monthlyBasic = $structure ? (float) $structure->basic_salary : 0.0;

        // Months the employee is still expected to be paid for: what is left
        // of the year, unless they have already left.
        $monthsAhead = $this->monthsRemaining($financialYear, $asOf, $employee);

        return [
            'months_paid' => $monthsPaid,
            'paid_gross' => $paidGross,
            'months_ahead' => $monthsAhead,
            'monthly_gross' => round($monthlyGross, 2),
            'expected_gross' => round($monthlyGross * $monthsAhead, 2),
            'projected_gross' => round($paidGross + $monthlyGross * $monthsAhead, 2),
            // Basic for the whole year drives the house rent arithmetic, which
            // is defined on salary rather than on gross.
            'annual_basic' => round(
                $slips->sum('basic_salary') + $monthlyBasic * $monthsAhead,
                2,
            ),
        ];
    }

    /**
     * Income the employee asked to have taken into account.
     *
     * @return array<string, mixed>
     */
    public function additions(?TaxDeclaration $declaration, string $regime): array
    {
        $lines = [];
        $total = 0.0;

        foreach (TaxDeductionSections::additions() as $key => $section) {
            if (! in_array($regime, $section['regimes'], true)) {
                continue;
            }

            $amount = $this->declaredAmount($declaration, $key);

            if ($amount <= 0) {
                continue;
            }

            $lines[] = ['section' => $key, 'label' => $section['label'], 'amount' => $amount];
            $total += $amount;
        }

        return ['lines' => $lines, 'total' => round($total, 2)];
    }

    /**
     * Every relief, capped where the Act caps it.
     *
     * A line that does not survive the chosen regime is still listed, with a
     * zero against it and the reason — an employee comparing regimes is
     * deciding exactly this, and hiding what they lose makes the comparison
     * unreadable.
     *
     * @return array<string, mixed>
     */
    public function exemptions(
        Employee $employee,
        ?TaxDeclaration $declaration,
        string $regime,
        float $annualBasic,
    ): array {
        $senior = $this->isSenior($employee);
        $lines = [];
        $pools = [];
        $total = 0.0;

        foreach (TaxDeductionSections::reliefs() as $key => $section) {
            $declared = $this->declaredAmount($declaration, $key);
            $applies = in_array($regime, $section['regimes'], true);

            // Nothing declared is nothing to say. A section the employee did
            // claim under is always shown, even where the regime disallows it,
            // because seeing what a regime costs you is the comparison.
            if ($declared <= 0) {
                continue;
            }

            $ceiling = TaxDeductionSections::ceilingFor($key, $senior);
            $allowed = $declared;
            $reason = null;

            if (($section['computed'] ?? false) && $key === 'hra') {
                $hra = $this->houseRentExemption($employee, $declaration, $annualBasic, $declared);
                $allowed = $hra['exempt'];
                $reason = $hra['reason'];
            } elseif ($ceiling !== null && $allowed > $ceiling) {
                $allowed = $ceiling;
                $reason = 'Capped at the section’s ceiling.';
            }

            // 80C and its companions draw on one pot between them.
            if ($applies && ($pool = $section['shares_ceiling_with'] ?? null)) {
                $poolCeiling = TaxDeductionSections::ceilingFor($key, $senior) ?? 0.0;
                $used = $pools[$pool] ?? 0.0;
                $room = max(0.0, $poolCeiling - $used);

                if ($allowed > $room) {
                    $allowed = $room;
                    $reason = 'The combined ceiling these sections share is already used up.';
                }

                $pools[$pool] = $used + $allowed;
            }

            if (! $applies) {
                $allowed = 0.0;
                $reason = 'Not allowed under the '.strtolower(TaxRegimes::label($regime)).'.';
            }

            $lines[] = [
                'section' => $key,
                'label' => $section['label'],
                'declared' => round($declared, 2),
                'allowed' => round($allowed, 2),
                'ceiling' => $ceiling,
                'applies' => $applies,
                'reason' => $reason,
            ];

            $total += $allowed;
        }

        return ['lines' => $lines, 'total' => round($total, 2)];
    }

    /**
     * The least of three figures, which is what section 10(13A) actually says.
     *
     * There is no house rent allowance component in most structures here, so
     * the allowance itself is read from the salary structure where one exists
     * and treated as nothing where it does not — in which case the exemption
     * is nil however much rent was paid, which is the law rather than an
     * oversight.
     *
     * @return array{exempt: float, reason: string, workings: array<string, float>}
     */
    public function houseRentExemption(
        Employee $employee,
        ?TaxDeclaration $declaration,
        float $annualBasic,
        float $annualRent,
    ): array {
        $allowance = $this->annualHouseRentAllowance($employee);

        if ($allowance <= 0) {
            return [
                'exempt' => 0.0,
                'reason' => 'No house rent allowance is paid, so none of the rent is exempt.',
                'workings' => ['allowance' => 0.0, 'share_of_salary' => 0.0, 'rent_over_tenth' => 0.0],
            ];
        }

        $share = ($declaration?->metro ? self::HRA_METRO_SHARE : self::HRA_NON_METRO_SHARE) * $annualBasic;
        $rentOverTenth = max(0.0, $annualRent - self::HRA_RENT_THRESHOLD_SHARE * $annualBasic);

        $workings = [
            'allowance' => round($allowance, 2),
            'share_of_salary' => round($share, 2),
            'rent_over_tenth' => round($rentOverTenth, 2),
        ];

        $exempt = round(min($workings), 2);

        return [
            'exempt' => $exempt,
            'reason' => 'The least of the allowance paid, '
                .($declaration?->metro ? 'half' : 'two fifths')
                .' of salary, and the rent above a tenth of salary.',
            'workings' => $workings,
        ];
    }

    /** The year's house rent allowance, from whichever component carries it. */
    public function annualHouseRentAllowance(Employee $employee): float
    {
        $structure = $this->structureFor($employee);

        if (! $structure) {
            return 0.0;
        }

        $line = collect($this->payroll->resolveComponentAmounts($structure, 1.0))
            ->firstWhere('code', 'HRA');

        return round(((float) ($line['amount'] ?? 0)) * 12, 2);
    }

    /**
     * The tax on a taxable income, band by band.
     *
     * @return array<string, mixed>
     */
    public function taxOn(float $taxableIncome, array $rules, ?Employee $employee = null, ?int $year = null): array
    {
        $slabs = $this->slabsFor($rules, $employee, $year);

        $bands = [];
        $tax = 0.0;
        $floor = 0.0;

        foreach ($slabs as $slab) {
            $ceiling = $slab['ceiling'];
            $top = $ceiling === null ? $taxableIncome : min($taxableIncome, (float) $ceiling);
            $inBand = round(max(0.0, $top - $floor), 2);

            if ($inBand > 0) {
                $amount = round($inBand * $slab['rate'], 2);
                $tax += $amount;

                $bands[] = [
                    'from' => $floor,
                    'to' => $ceiling === null ? null : (float) $ceiling,
                    'rate' => $slab['rate'],
                    'income' => $inBand,
                    'tax' => $amount,
                ];
            }

            if ($ceiling === null || $taxableIncome <= $ceiling) {
                break;
            }

            $floor = (float) $ceiling;
        }

        $tax = round($tax, 2);

        $rebate = $this->rebate($taxableIncome, $tax, $rules);
        $afterRebate = round(max(0.0, $tax - $rebate), 2);

        $surcharge = $this->surcharge($taxableIncome, $afterRebate, $rules);
        $cess = round(($afterRebate + $surcharge['amount']) * TaxRegimes::CESS_RATE, 2);

        return [
            'bands' => $bands,
            'before_rebate' => $tax,
            'rebate' => $rebate,
            'after_rebate' => $afterRebate,
            'surcharge' => $surcharge['amount'],
            'surcharge_rate' => $surcharge['rate'],
            'surcharge_relief' => $surcharge['relief'],
            'cess' => $cess,
            'cess_rate' => TaxRegimes::CESS_RATE,
            'total' => round($afterRebate + $surcharge['amount'] + $cess, 2),
        ];
    }

    /**
     * The slabs, with the first one raised for somebody over sixty.
     *
     * Age only moves the point at which tax starts, and only under the old
     * regime — the new one taxes everybody from the same rupee.
     */
    protected function slabsFor(array $rules, ?Employee $employee, ?int $year): array
    {
        $slabs = $rules['slabs'];
        $exemptions = $rules['senior_exemptions'] ?? [];

        if (! $employee || ! $exemptions) {
            return $slabs;
        }

        $age = $this->ageAtYearEnd($employee, $year);

        foreach ($exemptions as $band) {
            if ($age >= $band['from_age']) {
                // The nil band is widened; any band it swallows disappears.
                $raised = [['ceiling' => (float) $band['exemption'], 'rate' => 0.0]];

                foreach ($slabs as $slab) {
                    if ($slab['ceiling'] === null || $slab['ceiling'] > $band['exemption']) {
                        $raised[] = $slab;
                    }
                }

                return $raised;
            }
        }

        return $slabs;
    }

    /**
     * Section 87A, with the marginal relief that stops the cliff.
     *
     * Without it a rupee of income over the ceiling costs tens of thousands in
     * tax. The relief limits the extra tax to the extra income, which is what
     * makes the band just above the ceiling behave sensibly.
     */
    protected function rebate(float $taxableIncome, float $tax, array $rules): float
    {
        $rebate = $rules['rebate'] ?? null;

        if (! $rebate) {
            return 0.0;
        }

        if ($taxableIncome <= $rebate['ceiling']) {
            return round(min($tax, (float) $rebate['maximum']), 2);
        }

        $over = $taxableIncome - $rebate['ceiling'];

        return $tax > $over ? round($tax - $over, 2) : 0.0;
    }

    /**
     * Surcharge on a large income, and the marginal relief beside it.
     *
     * The same cliff, one order of magnitude up: crossing fifty lakh by a
     * rupee would add a tenth of the whole tax bill. Relief caps the increase
     * at the income that crossed the line.
     *
     * @return array{rate: float, amount: float, relief: float}
     */
    protected function surcharge(float $taxableIncome, float $tax, array $rules): array
    {
        $bands = $rules['surcharge'] ?? [];
        $rate = 0.0;
        $threshold = 0.0;
        $previousRate = 0.0;

        foreach ($bands as $band) {
            if ($band['ceiling'] === null || $taxableIncome <= $band['ceiling']) {
                $rate = $band['rate'];
                break;
            }

            $previousRate = $band['rate'];
            $threshold = (float) $band['ceiling'];
        }

        if ($rate <= 0 || $tax <= 0) {
            return ['rate' => 0.0, 'amount' => 0.0, 'relief' => 0.0];
        }

        $amount = round($tax * $rate, 2);

        // What somebody just under the threshold would have paid, so the
        // increase can be held to the income that took them over it.
        $taxAtThreshold = $this->taxAtThreshold($threshold, $rules);
        $surchargeAtThreshold = round($taxAtThreshold * $previousRate, 2);
        $ceilingOnTotal = $taxAtThreshold + $surchargeAtThreshold + ($taxableIncome - $threshold);
        $relief = round(max(0.0, ($tax + $amount) - $ceilingOnTotal), 2);

        return [
            'rate' => $rate,
            'amount' => round(max(0.0, $amount - $relief), 2),
            'relief' => $relief,
        ];
    }

    /** Tax on exactly a surcharge threshold, for working out marginal relief. */
    protected function taxAtThreshold(float $threshold, array $rules): float
    {
        $tax = 0.0;
        $floor = 0.0;

        foreach ($rules['slabs'] as $slab) {
            $ceiling = $slab['ceiling'];
            $top = $ceiling === null ? $threshold : min($threshold, (float) $ceiling);
            $tax += max(0.0, $top - $floor) * $slab['rate'];

            if ($ceiling === null || $threshold <= $ceiling) {
                break;
            }

            $floor = (float) $ceiling;
        }

        return round($tax, 2);
    }

    /** Tax already taken from this year's payslips. */
    public function alreadyDeducted(Employee $employee, int $financialYear): float
    {
        return round((float) Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('period_start', [
                FinancialYear::start($financialYear),
                FinancialYear::end($financialYear),
            ])
            ->sum('tax_deducted'), 2);
    }

    /**
     * Months of the year still to be paid, counting the one being run.
     *
     * Never zero while the year is open: spreading what is left over nothing
     * would divide by zero, and spreading it over the current month is what
     * actually happens in March.
     */
    public function monthsRemaining(int $financialYear, ?Carbon $asOf = null, ?Employee $employee = null): int
    {
        $start = FinancialYear::start($financialYear);
        $end = FinancialYear::end($financialYear);
        $asOf = $asOf ? $asOf->copy() : Carbon::today();

        if ($asOf->lt($start)) {
            $asOf = $start->copy();
        }

        if ($employee?->date_of_exit && $employee->date_of_exit->lt($end)) {
            $end = $employee->date_of_exit->copy()->endOfMonth();
        }

        if ($asOf->gt($end)) {
            return 0;
        }

        return max(0, $asOf->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth()) + 1);
    }

    public function declarationFor(Employee $employee, int $financialYear): ?TaxDeclaration
    {
        return TaxDeclaration::with('items')
            ->where('employee_id', $employee->id)
            ->where('financial_year', $financialYear)
            ->first();
    }

    protected function declaredAmount(?TaxDeclaration $declaration, string $section): float
    {
        $item = $declaration?->items->firstWhere('section', $section);

        return $item ? $item->effectiveAmount() : 0.0;
    }

    protected function structureFor(Employee $employee, ?Carbon $asOf = null): ?SalaryStructure
    {
        $asOf ??= Carbon::today();

        return SalaryStructure::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('effective_from', '<=', $asOf)
            ->orderByDesc('effective_from')
            ->first()
            ?? SalaryStructure::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->orderByDesc('effective_from')
                ->first();
    }

    protected function isSenior(Employee $employee): bool
    {
        return $this->ageAtYearEnd($employee, null) >= 60;
    }

    /** Age on the last day of the financial year, which is what the Act uses. */
    protected function ageAtYearEnd(Employee $employee, ?int $year): int
    {
        if (! $employee->date_of_birth) {
            return 0;
        }

        $end = FinancialYear::end($year ?? FinancialYear::current());

        return (int) $employee->date_of_birth->diffInYears($end);
    }
}
