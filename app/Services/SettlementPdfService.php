<?php

namespace App\Services;

use App\Models\Settlement;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;

/**
 * The statement somebody is handed on their last day.
 *
 * On the same pad as every other document this system issues, and built
 * entirely from the frozen row: nothing here consults today's salary
 * structure, today's leave balance or today's statutory cap, because the
 * statement has to say the same thing in a year's time as it does now.
 */
class SettlementPdfService
{
    public function make(Settlement $settlement): PdfWrapper
    {
        $settlement->loadMissing([
            'employee.branch', 'employee.department', 'employee.designation',
            'company', 'lines', 'approver',
        ]);

        return Pdf::loadView('pdf.settlement', [
            'settlement' => $settlement,
            'employee' => $settlement->employee,
            'company' => app(Letterhead::class)->forCompany($settlement->company),
        ])->setPaper('a4');
    }
}
