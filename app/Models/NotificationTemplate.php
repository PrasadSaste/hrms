<?php

namespace App\Models;

use App\Support\NotificationEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * An administrator's changes to one notification event.
 *
 * Rows are overrides, not the whole story: anything left null falls back to the
 * wording shipped in NotificationEvents, so the app still sends a sensible
 * message even when nobody has touched this screen. The action link is
 * deliberately not editable because it points at a route inside the portal.
 */
class NotificationTemplate extends Model
{
    protected $fillable = [
        'key',
        'mail_enabled',
        'database_enabled',
        'subject',
        'body',
        'action_label',
        'title',
        'message',
        'updated_by',
    ];

    protected const CACHE_KEY = 'hrms.notification-templates';

    protected function casts(): array
    {
        return [
            'mail_enabled' => 'boolean',
            'database_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Every override, keyed by event.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->get()->mapWithKeys(fn (self $t) => [
                $t->key => [
                    'mail_enabled' => $t->mail_enabled,
                    'database_enabled' => $t->database_enabled,
                    'subject' => $t->subject,
                    'body' => $t->body,
                    'action_label' => $t->action_label,
                    'title' => $t->title,
                    'message' => $t->message,
                    'updated_at' => $t->updated_at,
                ],
            ])->all();
        });
    }

    /**
     * The catalogue entry for one event with any overrides applied.
     *
     * @return array<string, mixed>|null
     */
    public static function resolve(string $key): ?array
    {
        $event = NotificationEvents::find($key);

        if (! $event) {
            return null;
        }

        $override = self::overrides()[$key] ?? [];
        $default = [
            'subject' => $event['email']['subject'] ?? null,
            'body' => $event['email']['body'] ?? null,
            'action_label' => $event['email']['action']['label'] ?? null,
            'title' => $event['database']['title'] ?? null,
            'message' => $event['database']['message'] ?? null,
        ];

        $resolved = [];
        $customised = false;

        foreach ($default as $field => $value) {
            $edited = $override[$field] ?? null;
            $resolved[$field] = self::blank($edited) ? $value : $edited;
            $customised = $customised || (! self::blank($edited) && $edited !== $value);
        }

        return array_merge($resolved, [
            'key' => $key,
            'group' => $event['group'],
            'label' => $event['label'],
            'description' => $event['description'],
            'channels' => $event['channels'],
            'placeholders' => NotificationEvents::placeholdersFor($key),
            'action_url' => $event['email']['action']['url'] ?? null,
            'mail_enabled' => self::channelEnabled($key, 'mail', $override),
            'database_enabled' => self::channelEnabled($key, 'database', $override),
            'customised' => $customised,
            'updated_at' => $override['updated_at'] ?? null,
        ]);
    }

    /** Every event, resolved, in catalogue order. */
    public static function resolveAll(): array
    {
        $resolved = [];

        foreach (NotificationEvents::keys() as $key) {
            $resolved[$key] = self::resolve($key);
        }

        return $resolved;
    }

    /** Resolved events keyed by the group they belong to. */
    public static function resolveGrouped(): array
    {
        $grouped = [];

        foreach (self::resolveAll() as $key => $event) {
            $grouped[$event['group']][$key] = $event;
        }

        return $grouped;
    }

    /**
     * Whether an event may go out over a channel.
     *
     * A channel the event does not support is always off, whatever the row
     * says, so switching a channel on can never invent a message the app has
     * no content for.
     */
    public static function channelEnabled(string $key, string $channel, ?array $override = null): bool
    {
        if (! NotificationEvents::supports($key, $channel)) {
            return false;
        }

        $override ??= self::overrides()[$key] ?? [];

        return (bool) ($override[$channel.'_enabled'] ?? true);
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
