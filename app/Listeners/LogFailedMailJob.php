<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * Surfaces queued mail failures in the application log.
 *
 * A failed job lands in the failed_jobs table, which nobody watches day to day.
 * Writing it to the log too means a mail server problem shows up wherever the
 * team already looks for errors.
 */
class LogFailedMailJob
{
    public function handle(JobFailed $event): void
    {
        $name = $event->job->resolveName();

        if (! str_contains($name, 'SendQueuedMailable') && ! str_contains($name, '\\Mail\\')) {
            return;
        }

        $payload = $event->job->payload();
        $mailable = $payload['data']['commandName'] ?? $name;

        Log::error('Queued email failed to send', [
            'mailable' => $mailable,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
            'error' => $event->exception->getMessage(),
        ]);
    }
}
