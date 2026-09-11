<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Services\NotificationTemplateRenderer;
use App\Support\NotificationEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Every in-app notification, built from the template stored for its event.
 *
 * The payload keeps the shape the bell menu and the notifications screen
 * already read: type, title, message, url and icon, with any extra identifiers
 * the caller wants to record alongside them.
 */
class TemplatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string>  $data  placeholder => value
     * @param  array<string, mixed>  $payload  extra keys stored with the notification
     */
    public function __construct(
        public string $eventKey,
        public array $data = [],
        public array $payload = [],
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationTemplate::channelEnabled($this->eventKey, 'database') ? ['database'] : [];
    }

    public function toArray(object $notifiable): array
    {
        $rendered = app(NotificationTemplateRenderer::class)->database($this->eventKey, $this->data);

        return array_merge([
            'type' => $this->eventKey,
            'title' => $rendered['title'],
            'message' => $rendered['message'],
            'url' => $rendered['url'],
            'icon' => NotificationEvents::icon($this->eventKey),
        ], $this->payload);
    }
}
