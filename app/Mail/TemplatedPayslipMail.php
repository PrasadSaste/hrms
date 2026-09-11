<?php

namespace App\Mail;

use App\Models\Payslip;
use App\Services\PayslipPdfService;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Headers;

/**
 * The salary slip email: the managed template plus the PDF itself.
 *
 * It keeps the X-HRMS-Payslip header so RecordPayslipDelivery can stamp
 * emailed_at once the message has actually left.
 */
class TemplatedPayslipMail extends TemplatedMail
{
    public function __construct(
        public Payslip $payslip,
        string $eventKey,
        array $data = [],
        array $overrides = [],
    ) {
        parent::__construct($eventKey, $data, $overrides);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-HRMS-Payslip' => (string) $this->payslip->id,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $service = app(PayslipPdfService::class);

        return [
            Attachment::fromData(
                fn () => $service->bytes($this->payslip),
                $service->filename($this->payslip),
            )->withMime('application/pdf'),
        ];
    }
}
