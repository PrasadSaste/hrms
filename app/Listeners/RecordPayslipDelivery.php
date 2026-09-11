<?php

namespace App\Listeners;

use App\Models\Payslip;
use Illuminate\Mail\Events\MessageSent;

/**
 * Stamps a payslip as emailed only once the message has actually been handed
 * to the mail server.
 *
 * Payslip mail is queued, so the request that triggers it returns long before
 * delivery is attempted. Recording the timestamp at queue time would tell
 * payroll a slip had been sent when it might still fail in the worker.
 */
class RecordPayslipDelivery
{
    public function handle(MessageSent $event): void
    {
        $header = $event->message->getHeaders()->get('X-HRMS-Payslip');

        if (! $header) {
            return;
        }

        $id = (int) $header->getBodyAsString();

        if ($id > 0) {
            Payslip::whereKey($id)->update(['emailed_at' => now()]);
        }
    }
}
