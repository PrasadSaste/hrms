<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\TemplatedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The one road every notification takes out of the application.
 *
 * Callers say what happened and hand over the values for the placeholders; the
 * dispatcher decides whether that event is switched on for email, for the bell
 * menu, or for neither, and renders the wording an administrator has chosen.
 * Email failures are logged rather than thrown, so a mail outage can never
 * block an approval or a payroll run.
 */
class NotificationDispatcher
{
    /** Send an event to a user over every channel it is switched on for. */
    public function toUser(
        string $key,
        ?User $user,
        array $data = [],
        array $payload = [],
        ?TemplatedMail $mailable = null,
    ): void {
        if (! $user) {
            return;
        }

        $data += ['recipient_name' => $user->name];

        $this->database($key, $user, $data, $payload);
        $this->toAddress($key, $user->email, $data, $mailable);
    }

    /** Send an event to several users at once. */
    public function toUsers(string $key, iterable $users, array $data = [], array $payload = []): void
    {
        foreach ($users as $user) {
            $this->toUser($key, $user, $data, $payload);
        }
    }

    /**
     * Email an event to an address that may not have a login behind it, which
     * is how a new employee hears about their own account.
     */
    public function toAddress(
        string $key,
        ?string $email,
        array $data = [],
        ?TemplatedMail $mailable = null,
    ): bool {
        if (! $email || ! $this->mailEnabled($key)) {
            return false;
        }

        return $this->deliver($email, $mailable ?? new TemplatedMail($key, $data));
    }

    /** Record an event in a user's bell menu. */
    public function database(string $key, ?User $user, array $data = [], array $payload = []): bool
    {
        if (! $user || ! $this->databaseEnabled($key)) {
            return false;
        }

        $user->notify(new TemplatedNotification($key, $data, $payload));

        return true;
    }

    /**
     * Whether email is on for this event: the event's own switch, and the
     * master switch on the settings screen.
     */
    public function mailEnabled(string $key): bool
    {
        return NotificationTemplate::channelEnabled($key, 'mail')
            && (bool) Setting::get('email_notifications_enabled', true);
    }

    public function databaseEnabled(string $key): bool
    {
        return NotificationTemplate::channelEnabled($key, 'database');
    }

    /** Put a message on its way, swallowing and logging transport errors. */
    public function deliver(string $email, object $mailable): bool
    {
        try {
            Mail::to($email)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('HRMS email failed', [
                'to' => $email,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
