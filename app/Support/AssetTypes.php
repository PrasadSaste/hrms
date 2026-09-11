<?php

namespace App\Support;

/**
 * What the company hands out and expects back.
 *
 * A catalogue like every other, and for the same reason: what is worth
 * recording differs completely by kind. A laptop has a serial number, a SIM
 * card has a number that is dialled, an access card has a badge number and
 * nothing else. Asking for all of them on one form would make three quarters
 * of it blank on every asset.
 *
 * `returnable` is the one that changes behaviour rather than wording: a laptop
 * comes back on the last day and a pair of safety boots does not, so a leaver
 * is only ever chased for the things a company actually wants returned.
 */
final class AssetTypes
{
    public const LAPTOP = 'laptop';

    public const DESKTOP = 'desktop';

    public const MONITOR = 'monitor';

    public const PHONE = 'phone';

    public const SIM = 'sim';

    public const ACCESS_CARD = 'access_card';

    public const VEHICLE = 'vehicle';

    public const FURNITURE = 'furniture';

    public const SOFTWARE = 'software';

    public const OTHER = 'other';

    /**
     * @return array<string, array{
     *     label: string, description: string, returnable: bool, icon: string,
     *     fields: array<string, array{label: string, type: string, required: bool}>
     * }>
     */
    public static function all(): array
    {
        return [
            self::LAPTOP => [
                'label' => 'Laptop',
                'description' => 'A portable computer issued to one person.',
                'returnable' => true,
                'icon' => 'laptop',
                'fields' => [
                    'serial_number' => ['label' => 'Serial number', 'type' => 'text', 'required' => true],
                    'processor' => ['label' => 'Processor', 'type' => 'text', 'required' => false],
                    'memory' => ['label' => 'Memory', 'type' => 'text', 'required' => false],
                    'storage' => ['label' => 'Storage', 'type' => 'text', 'required' => false],
                    'operating_system' => ['label' => 'Operating system', 'type' => 'text', 'required' => false],
                ],
            ],

            self::DESKTOP => [
                'label' => 'Desktop computer',
                'description' => 'A fixed workstation, usually tied to a desk rather than a person.',
                'returnable' => true,
                'icon' => 'desktop',
                'fields' => [
                    'serial_number' => ['label' => 'Serial number', 'type' => 'text', 'required' => true],
                    'processor' => ['label' => 'Processor', 'type' => 'text', 'required' => false],
                    'memory' => ['label' => 'Memory', 'type' => 'text', 'required' => false],
                ],
            ],

            self::MONITOR => [
                'label' => 'Monitor',
                'description' => 'A display, and anything else that plugs into a desk.',
                'returnable' => true,
                'icon' => 'monitor',
                'fields' => [
                    'serial_number' => ['label' => 'Serial number', 'type' => 'text', 'required' => true],
                    'size' => ['label' => 'Screen size', 'type' => 'text', 'required' => false],
                ],
            ],

            self::PHONE => [
                'label' => 'Mobile phone',
                'description' => 'A handset. The connection on it is a SIM card, recorded separately.',
                'returnable' => true,
                'icon' => 'phone',
                'fields' => [
                    'imei' => ['label' => 'IMEI', 'type' => 'text', 'required' => true],
                    'serial_number' => ['label' => 'Serial number', 'type' => 'text', 'required' => false],
                ],
            ],

            self::SIM => [
                'label' => 'SIM card',
                'description' => 'A company connection. Kept apart from the handset because the two move separately.',
                'returnable' => true,
                'icon' => 'sim',
                'fields' => [
                    'mobile_number' => ['label' => 'Mobile number', 'type' => 'text', 'required' => true],
                    'operator' => ['label' => 'Operator', 'type' => 'text', 'required' => false],
                    'plan' => ['label' => 'Plan', 'type' => 'text', 'required' => false],
                ],
            ],

            self::ACCESS_CARD => [
                'label' => 'Access card',
                'description' => 'The card that opens the doors. The one thing that must never leave with somebody.',
                'returnable' => true,
                'icon' => 'card',
                'fields' => [
                    'card_number' => ['label' => 'Card number', 'type' => 'text', 'required' => true],
                    'zones' => ['label' => 'Doors or zones it opens', 'type' => 'text', 'required' => false],
                ],
            ],

            self::VEHICLE => [
                'label' => 'Vehicle',
                'description' => 'A company car, van or two-wheeler.',
                'returnable' => true,
                'icon' => 'vehicle',
                'fields' => [
                    'registration_number' => ['label' => 'Registration number', 'type' => 'text', 'required' => true],
                    'chassis_number' => ['label' => 'Chassis number', 'type' => 'text', 'required' => false],
                    'insurance_expires_on' => ['label' => 'Insurance expires on', 'type' => 'date', 'required' => false],
                ],
            ],

            self::FURNITURE => [
                'label' => 'Furniture',
                'description' => 'A chair, a desk, a cabinet. Belongs to a place more often than to a person.',
                'returnable' => true,
                'icon' => 'furniture',
                'fields' => [
                    'asset_code' => ['label' => 'Asset code', 'type' => 'text', 'required' => false],
                ],
            ],

            self::SOFTWARE => [
                'label' => 'Software licence',
                'description' => 'A seat on a paid tool. Worth reclaiming on the last day as much as a laptop is.',
                'returnable' => true,
                'icon' => 'software',
                'fields' => [
                    'licence_key' => ['label' => 'Licence key or account', 'type' => 'text', 'required' => false],
                    'seats' => ['label' => 'Seats', 'type' => 'text', 'required' => false],
                    'renews_on' => ['label' => 'Renews on', 'type' => 'date', 'required' => false],
                ],
            ],

            self::OTHER => [
                'label' => 'Other',
                'description' => 'Anything the list above does not cover.',
                'returnable' => true,
                'icon' => 'box',
                'fields' => [
                    'serial_number' => ['label' => 'Serial or reference number', 'type' => 'text', 'required' => false],
                ],
            ],
        ];
    }

    /** @return array<string, array{label: string, type: string, required: bool}>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<int, string> */
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
        return self::all()[$key]['icon'] ?? 'box';
    }

    /** Whether the company expects this kind back when somebody leaves. */
    public static function returnable(string $key): bool
    {
        return self::all()[$key]['returnable'] ?? true;
    }

    /** @return array<string, array{label: string, type: string, required: bool}> */
    public static function fieldsFor(string $key): array
    {
        return self::all()[$key]['fields'] ?? [];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn (array $type) => $type['label'], self::all());
    }
}
