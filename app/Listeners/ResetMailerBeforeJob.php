<?php

namespace App\Listeners;

use App\Services\MailSettings;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Gives every queued job a fresh SMTP connection.
 *
 * A queue worker keeps the mailer, and with it the SMTP socket, alive between
 * jobs. Amazon SES closes a connection that sits idle for around 20 seconds and
 * answers the next command with "451 4.4.2 Timeout waiting for data from
 * client", but Symfony only checks the connection after 100 seconds of
 * silence. Any email queued more than a few seconds after the previous one
 * therefore failed on the worker. Dropping the resolved mailers before each
 * job means the next message reconnects, which costs well under a second.
 */
class ResetMailerBeforeJob
{
    public function __construct(protected MailSettings $settings) {}

    public function handle(JobProcessing $event): void
    {
        // A long-running worker holds the config it booted with. Re-applying
        // means an SMTP server changed on the settings screen takes effect on
        // the next job rather than on the next deployment.
        $this->settings->apply();

        $manager = app('mail.manager');

        // Only a real SMTP mailer holds a connection worth dropping; a fake in
        // tests, or the array and log transports, keep their messages in memory.
        if (! $manager instanceof MailManager) {
            return;
        }

        $default = config('mail.default');

        if (config("mail.mailers.{$default}.transport") !== 'smtp') {
            return;
        }

        $manager->forgetMailers();
    }
}
