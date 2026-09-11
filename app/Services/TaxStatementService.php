<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payslip;
use App\Support\FinancialYear;
use App\Support\TaxRegimes;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;

/**
 * The annual statement of what somebody earned and what was deducted.
 *
 * **This is not a Form 16, and does not pretend to be one.** A Form 16 has two
 * parts: Part A, which carries the deductor's TAN, the challan identification
 * numbers and the quarterly figures the department itself holds, is generated
 * by TRACES from the returns actually filed — it is not ours to invent, and a
 * document that looked official while carrying figures nobody had filed would
 * be worse than no document at all. Part B is the salary breakdown and the tax
 * computation, which is exactly what this system knows.
 *
 * So this issues the Part B content under its own name, says on its face what
 * it is and what it is not, and tells the employee where the real certificate
 * comes from. It is the right document for checking a return against, and the
 * wrong one to attach to it.
 *
 * Built from the payslips, not from today's salary structure: a statement for
 * a year that has closed must say the same thing next year as it does now.
 */
class TaxStatementService
{
    public function __construct(protected IncomeTaxService $tax) {}

    /**
     * The year's figures, gathered from what was actually paid.
     *
     * @return array<string, mixed>
     */
    public function gather(Employee $employee, int $financialYear): array
    {
        $slips = Payslip::query()
            ->with(['items', 'company'])
            ->where('employee_id', $employee->id)
            ->whereBetween('period_start', [
                FinancialYear::start($financialYear),
                FinancialYear::end($financialYear),
            ])
            ->orderBy('period_start')
            ->get();

        // Earnings and deductions summed by code across the year, so the
        // statement reads as one salary rather than twelve payslips.
        $earnings = [];
        $deductions = [];

        foreach ($slips as $slip) {
            foreach ($slip->items as $item) {
                $bucket = $item->type->value === 'earning' ? 'earnings' : 'deductions';
                $key = $item->code;

                ${$bucket}[$key] ??= ['code' => $key, 'name' => $item->name, 'amount' => 0.0];
                ${$bucket}[$key]['amount'] = round(${$bucket}[$key]['amount'] + $item->amount, 2);
            }
        }

        // The overtime line's name carries that month's hours, which makes no
        // sense once twelve are added together.
        if (isset($earnings['OT'])) {
            $earnings['OT']['name'] = 'Overtime';
        }

        $declaration = $this->tax->declarationFor($employee, $financialYear);
        $regime = $declaration?->regime
            ?? $slips->last()?->tax_regime
            ?? TaxRegimes::default();

        return [
            'employee' => $employee,
            'financial_year' => $financialYear,
            'year_label' => FinancialYear::label($financialYear),
            'assessment_year' => ($financialYear + 1).'–'.substr((string) ($financialYear + 2), 2),
            'slips' => $slips,
            'months' => $slips->count(),
            // The company that actually paid, taken from the slips rather than
            // from the employee's record today: somebody moved between two
            // entities in October was paid by both.
            'companies' => $slips->pluck('company')->filter()->unique('id')->values(),
            'earnings' => array_values($earnings),
            'deductions' => array_values($deductions),
            'gross' => round($slips->sum('gross_earnings'), 2),
            'total_deductions' => round($slips->sum('total_deductions'), 2),
            'net_paid' => round($slips->sum('net_pay'), 2),
            'tax_deducted' => round($slips->sum('tax_deducted'), 2),
            'regime' => $regime,
            'regime_label' => TaxRegimes::label($regime),
            'declaration' => $declaration,
            // The computation as it stands at the close of the year, which is
            // the figure the deduction was working towards all along.
            'computation' => $this->tax->computeFor(
                $employee,
                $financialYear,
                $regime,
                FinancialYear::end($financialYear),
            ),
        ];
    }

    public function make(Employee $employee, int $financialYear): PdfWrapper
    {
        $data = $this->gather($employee, $financialYear);

        return Pdf::loadView('pdf.tax-statement', $data + [
            'company' => app(Letterhead::class)->forCompany(
                $data['companies']->first() ?? $employee->company,
            ),
        ])->setPaper('a4');
    }

    public function filename(Employee $employee, int $financialYear): string
    {
        return 'tax-statement-'.$employee->employee_code.'-'.$financialYear.'.pdf';
    }
}
