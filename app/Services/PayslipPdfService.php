<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Facades\Storage;

class PayslipPdfService
{
    /** Build the DomPDF document for a payslip. */
    public function make(Payslip $payslip): PdfWrapper
    {
        $payslip->loadMissing([
            'employee.branch', 'employee.department', 'employee.designation',
            'company', 'payroll', 'earnings', 'deductions',
        ]);

        return Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'employee' => $payslip->employee,
            'company' => $this->companyDetails($payslip->company),
        ])->setPaper('a4');
    }

    public function filename(Payslip $payslip): string
    {
        return sprintf(
            'payslip-%s-%s.pdf',
            $payslip->employee?->employee_code ?? $payslip->employee_id,
            $payslip->period_start->format('Y-m'),
        );
    }

    /** Render and persist the PDF, returning the storage path. */
    public function store(Payslip $payslip, string $disk = 'local'): string
    {
        $path = 'payslips/'.$payslip->period_start->format('Y/m').'/'.$this->filename($payslip);

        Storage::disk($disk)->put($path, $this->make($payslip)->output());

        $payslip->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    /** Raw PDF bytes, used when attaching a payslip to an email. */
    public function bytes(Payslip $payslip): string
    {
        return $this->make($payslip)->output();
    }

    /**
     * Whose name goes at the top of the slip.
     *
     * Shared with letters, which have the same question to answer.
     *
     * @return array<string, string|null>
     */
    public function companyDetails(?Company $company = null): array
    {
        return app(Letterhead::class)->forCompany($company);
    }

    public function logoDataUri(?string $path = null): ?string
    {
        return app(Letterhead::class)->logoDataUri($path);
    }
}
