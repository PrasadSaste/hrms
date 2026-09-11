<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Services\NotificationDispatcher;
use App\Services\NotificationTemplateRenderer;
use App\Support\NotificationEvents;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The forgotten-password email.
 *
 * Laravel owns the token and the link, so this stays a notification rather than
 * becoming a plain mailable; the words still come from the notification console
 * like every other message the HRMS sends.
 */
class ResetPasswordNotification extends ResetPassword
{
    /** Honour the console's switch for this event, and the master email switch. */
    public function via($notifiable): array
    {
        return app(NotificationDispatcher::class)->mailEnabled(NotificationEvents::PASSWORD_RESET_LINK)
            ? ['mail']
            : [];
    }

    public function toMail($notifiable): MailMessage
    {
        $renderer = app(NotificationTemplateRenderer::class);

        $rendered = $renderer->email(NotificationEvents::PASSWORD_RESET_LINK, [
            'user_name' => $notifiable->name,
            'recipient_name' => $notifiable->name,
            'reset_url' => $this->resetUrl($notifiable),
            'expires_in_minutes' => (string) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire'
            ),
        ]);

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->markdown('mail.templated', [
                'body' => $rendered['body'],
                'bodyHtml' => $renderer->html($rendered['body']),
                'actionLabel' => $rendered['action_label'],
                'actionUrl' => $rendered['action_url'],
                'companyName' => Setting::get('company_name', config('app.name')),
            ]);
    }

    protected function resetUrl($notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
