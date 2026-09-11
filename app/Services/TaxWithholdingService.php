<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Setting;
use App\Support\FinancialYear;
use App\Support\TaxRegimes;
use Illuminate\Support\Carbon;

/**
 * Whether a payslip carries tax, and how much.
 *
 * A thin thing on purpose. `IncomeTaxService` does the arithmetic; this
 * decides whether payroll should ask it at all, and hands back a figure the
 * payslip can carry. Keeping the two apart also keeps the container happy:
 * `IncomeTaxService` needs `PayrollService` to value a salary structure, and
 * `PayrollService` needs this — so this one resolves the other **when it is
 * called** rather than in its constructor, and the cycle never forms.
 *
 * **Deducting tax starts switched off.** An installation that has never
 * visited the payroll settings keeps paying salaries gross of tax, exactly as
 * it did before this existed. Taking money out of somebody's pay because the
 * software decided to is a worse failure than not taking it: the second is
 * noticed in April and corrected, the first is noticed on payday.
 */
class TaxWithholdingService
{
    public function enabled(): bool
    {
        return (bool) Setting::get('payroll_tds_enabled', false);
    }

    /**
     * Round a monthly deduction to whole rupees.
     *
     * Nobody deducts paise, the challan is filed in rupees, and twelve
     * roundings of a paisa are what make a Form 16 disagree with the payslips
     * behind it.
     */
    public function round(float $amount): float
    {
        return (float) round($amount);
    }

    /**
     * What to take from one payslip, and under which regime.
     *
     * @return array{enabled: bool, regime: ?string, amount: float, projection: ?array<string, mixed>}
     */
    public function forPayslip(Employee $employee, Payroll $payroll): array
    {
        $none = ['enabled' => false, 'regime' => null, 'amount' => 0.0, 'projection' => null];

        if (! $this->enabled()) {
            return $none;
        }

        $asOf = $payroll->period_end ? $payroll->period_end->copy() : Carbon::today();
        $year = FinancialYear::of($asOf);

        $tax = app(IncomeTaxService::class);
        $declaration = $tax->declarationFor($employee, $year);
        $regime = $declaration?->regime ?? TaxRegimes::default();

        // Worked out as at the end of the month being run, so a run for June
        // executed in September still spreads June's share over the months
        // that were left in June.
        $projection = $tax->computeFor($employee, $year, $regime, $asOf);

        return [
            'enabled' => true,
            'regime' => $regime,
            'amount' => max(0.0, $this->round($projection['monthly'])),
            'projection' => $projection,
        ];
    }
}
