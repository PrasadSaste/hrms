<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Sends every message to one inbox anywhere but production.
 *
 * A staging server is usually seeded from a copy of the real database, so an
 * approval, a payslip or an offer letter tested there would otherwise reach the
 * employee it names. Redirecting is done here rather than by Laravel's global
 * "to" so the addresses it replaced can be kept: they go onto the message as
 * X-Original-To, X-Original-Cc and X-Original-Bcc, which is the first thing you
 * want to know when a test message lands in the shared inbox.
 *
 * The production guard lives in config/mail.php, so an address left in a
 * production .env by mistake still cannot divert live mail.
 */
class RedirectOutgoingMail
{
    public function handle(MessageSending $event): bool
    {
        $redirect = config('mail.redirect.to');

        if (blank($redirect)) {
            return true;
        }

        $message = $event->message;

        $original = [
            'To' => $this->addresses($message->getTo()),
            'Cc' => $this->addresses($message->getCc()),
            'Bcc' => $this->addresses($message->getBcc()),
        ];

        // Nothing addressed to anybody: leave it alone rather than inventing a
        // recipient for a message that was never going to be delivered.
        if (blank(array_filter($original))) {
            return true;
        }

        $headers = $message->getHeaders();

        foreach ($original as $field => $addresses) {
            if ($addresses === []) {
                continue;
            }

            $name = 'X-Original-'.$field;

            if ($headers->has($name)) {
                $headers->remove($name);
            }

            $headers->addTextHeader($name, implode(', ', $addresses));
        }

        $message->to(new Address($redirect, (string) config('mail.redirect.name', '')));
        $message->cc();
        $message->bcc();

        Log::info('Outgoing mail redirected.', [
            'environment' => app()->environment(),
            'subject' => $message->getSubject(),
            'redirected_to' => $redirect,
            'original' => array_filter($original),
        ]);

        return true;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $address->toString(), $addresses);
    }
}
