<?php

namespace App\Mail;

use App\Models\Setting;
use App\Services\NotificationTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every HRMS email, built from the template stored for its event.
 *
 * The data is a flat array of already-formatted strings rather than models, so
 * the message queues cleanly and says the same thing whenever it is finally
 * sent. Wording is resolved at send time, which means an edit made while a
 * message sits on the queue still reaches the recipient.
 */
class TemplatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $eventKey  the catalogue key, e.g. leave.approved
     * @param  array<string, string>  $data  placeholder => value
     * @param  array<string, string>  $overrides  wording that has not been saved yet
     */
    public function __construct(
        public string $eventKey,
        public array $data = [],
        public array $overrides = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered()['subject']);
    }

    public function content(): Content
    {
        $rendered = $this->rendered();
        $renderer = app(NotificationTemplateRenderer::class);

        return new Content(
            markdown: 'mail.templated',
            text: 'mail.templated-text',
            with: [
                'body' => $rendered['body'],
                'bodyHtml' => $renderer->html($rendered['body']),
                'actionLabel' => $rendered['action_label'],
                'actionUrl' => $rendered['action_url'],
                'companyName' => Setting::get('company_name', config('app.name')),
            ],
        );
    }

    /** @return array{subject: string, body: string, action_label: string, action_url: string} */
    protected function rendered(): array
    {
        return app(NotificationTemplateRenderer::class)
            ->email($this->eventKey, $this->data, $this->overrides);
    }
}
