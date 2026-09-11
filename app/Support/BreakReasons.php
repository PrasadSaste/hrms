<?php

namespace App\Support;

/**
 * What somebody is doing when they are away from their desk.
 *
 * Recording the reason turns a break from a gap in the day into something the
 * team can read: an hour of client visits and an hour of lunch are both time
 * away, but only one of them is worth reporting on.
 *
 * Every reason is deducted from working time. Add an entry here and it appears
 * on the break picker and in the reports with no further work.
 */
final class BreakReasons
{
    public const LUNCH = 'lunch';

    public const OUTSIDE_MEETING = 'outside_meeting';

    public const CLIENT_VISIT = 'client_visit';

    public const CLIENT_CALL = 'client_call';

    public const PERSONAL_CALL = 'personal_call';

    public const TEAM_MEETING = 'team_meeting';

    public const PRODUCT_TRAINING = 'product_training';

    public const PAPERWORK = 'paperwork';

    public const OTHER = 'other';

    /** @return array<string, array{label: string, description: string, icon: string}> */
    public static function all(): array
    {
        return [
            self::LUNCH => [
                'label' => 'Lunch',
                'description' => 'The midday break.',
                'icon' => 'clock',
            ],
            self::OUTSIDE_MEETING => [
                'label' => 'Outside meeting',
                'description' => 'A meeting away from the office.',
                'icon' => 'users',
            ],
            self::CLIENT_VISIT => [
                'label' => 'Client visit',
                'description' => 'At a client’s premises.',
                'icon' => 'building',
            ],
            self::CLIENT_CALL => [
                'label' => 'Client — telephonic call',
                'description' => 'On a call with a client.',
                'icon' => 'badge',
            ],
            self::PERSONAL_CALL => [
                'label' => 'Personal telephonic call',
                'description' => 'A personal call.',
                'icon' => 'user',
            ],
            self::TEAM_MEETING => [
                'label' => 'Team meeting',
                'description' => 'An internal meeting.',
                'icon' => 'users',
            ],
            self::PRODUCT_TRAINING => [
                'label' => 'Product training',
                'description' => 'Learning or delivering training.',
                'icon' => 'layers',
            ],
            self::PAPERWORK => [
                'label' => 'Paperwork',
                'description' => 'Away from the system doing paperwork.',
                'icon' => 'document',
            ],
            self::OTHER => [
                'label' => 'Other',
                'description' => 'Anything else — say what in the comment.',
                'icon' => 'tag',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function icon(string $key): string
    {
        return self::all()[$key]['icon'] ?? 'clock';
    }

    /** For a select box: key => label. */
    public static function options(): array
    {
        return collect(self::all())->map(fn (array $r) => $r['label'])->all();
    }
}
