<?php

namespace App\Services;

use App\Models\Setting;

/**
 * The interface theme: three colours and the surface they sit on.
 *
 * The stylesheet declares --color-brand-*, --color-accent-* and
 * --color-tertiary-* as Tailwind theme tokens. Re-declaring those variables at
 * runtime re-themes every button, link, badge, icon and chart without
 * rebuilding the CSS, so the theme is a setting rather than a code change.
 *
 * Each colour is given as a single hex value and the ten shades are worked out
 * from it. Nobody should have to pick ten blues that agree with each other, and
 * a ramp derived by one rule cannot drift the way ten hand-picked values do.
 */
class BrandPalette
{
    /** Primary: what you press. Buttons, links, the active screen, the icon. */
    public const DEFAULT = '#2563eb';

    /** Secondary: supporting surfaces and the second series on a chart. */
    public const DEFAULT_SECONDARY = '#0d9488';

    /** Tertiary: highlights that are neither an action nor a status. */
    public const DEFAULT_TERTIARY = '#f59e0b';

    /**
     * Each colour's setting, its fallback, and the CSS token family it feeds.
     * Adding a role here is enough for the ramp, the settings screen and the
     * live preview to pick it up.
     */
    public const ROLES = [
        'primary' => ['setting' => 'brand_color', 'token' => 'brand', 'default' => self::DEFAULT],
        'secondary' => ['setting' => 'brand_secondary_color', 'token' => 'accent', 'default' => self::DEFAULT_SECONDARY],
        'tertiary' => ['setting' => 'brand_tertiary_color', 'token' => 'tertiary', 'default' => self::DEFAULT_TERTIARY],
    ];

    /** WCAG AA for text below 18.66px, which every link in here is. */
    public const TEXT_CONTRAST = 4.5;

    /** What the shell is painted on. */
    public const SURFACES = ['light', 'tinted', 'dark'];

    public const DEFAULT_SURFACE = 'light';

    /**
     * How far each step is mixed towards white (negative) or black (positive).
     * Step 600 is the colour itself, matching Tailwind's convention that the
     * 600 weight is the one you press.
     */
    private const STEPS = [
        50 => -0.95,
        100 => -0.89,
        200 => -0.77,
        300 => -0.60,
        400 => -0.36,
        500 => -0.16,
        600 => 0.0,
        700 => 0.14,
        800 => 0.28,
        900 => 0.42,
    ];

    /** One role's configured colour, falling back to its default. */
    public function color(string $role = 'primary'): string
    {
        $definition = self::ROLES[$role] ?? self::ROLES['primary'];
        $value = (string) Setting::get($definition['setting'], $definition['default']);

        return $this->isValid($value) ? strtolower($value) : $definition['default'];
    }

    /** @return array<string, string> role => hex */
    public function colors(): array
    {
        return collect(self::ROLES)
            ->keys()
            ->mapWithKeys(fn (string $role) => [$role => $this->color($role)])
            ->all();
    }

    /** Which of light, tinted or dark the shell is painted on. */
    public function surface(): string
    {
        $value = (string) Setting::get('theme_surface', self::DEFAULT_SURFACE);

        return in_array($value, self::SURFACES, true) ? $value : self::DEFAULT_SURFACE;
    }

    public function isValid(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * The full ramp for one colour, keyed by weight.
     *
     * @return array<int, string>
     */
    public function ramp(?string $hex = null, string $role = 'primary'): array
    {
        $base = $this->isValid($hex) ? $hex : $this->color($role);
        [$r, $g, $b] = $this->toRgb($base);

        $ramp = [];

        foreach (self::STEPS as $weight => $mix) {
            $ramp[$weight] = $mix < 0
                ? $this->toHex(
                    $this->blend($r, 255, -$mix),
                    $this->blend($g, 255, -$mix),
                    $this->blend($b, 255, -$mix),
                )
                : $this->toHex(
                    $this->blend($r, 0, $mix),
                    $this->blend($g, 0, $mix),
                    $this->blend($b, 0, $mix),
                );
        }

        return $ramp;
    }

    /**
     * Every ramp, plus the surface, as CSS custom property declarations.
     *
     * Passing a colour overrides that one role, which is what the settings
     * screen does to show a colour before it is saved.
     *
     * @param  array<string, string>  $overrides  role => hex
     */
    public function cssVariables(array $overrides = [], ?string $surface = null): string
    {
        $lines = [];

        foreach (self::ROLES as $role => $definition) {
            $hex = $overrides[$role] ?? null;

            foreach ($this->ramp($hex, $role) as $weight => $value) {
                $lines[] = sprintf('--color-%s-%d:%s', $definition['token'], $weight, $value);
            }

            $lines[] = sprintf(
                '--color-%s-foreground:%s',
                $definition['token'],
                $this->foregroundOn($hex ?: $this->color($role)),
            );

            $lines[] = sprintf(
                '--color-%s-ink:%s',
                $definition['token'],
                $this->ink($hex, $role),
            );
        }

        foreach ($this->surfaceVariables($surface, $overrides) as $name => $value) {
            $lines[] = $name.':'.$value;
        }

        return implode(';', $lines).';';
    }

    /**
     * The shell's own colours: the page behind the cards, the navigation
     * column, its icons and the pill behind the screen you are on.
     *
     * Light leaves the shell neutral. Tinted washes it with the faintest steps
     * of the secondary colour, so the theme reaches the background without
     * shouting. Dark inverts the navigation column the way a control room
     * would, and takes its darkest tones from the secondary colour too, so it
     * is the company's dark rather than a generic slate.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public function surfaceVariables(?string $surface = null, array $overrides = []): array
    {
        $surface = in_array($surface, self::SURFACES, true) ? $surface : $this->surface();

        $primary = $this->ramp($overrides['primary'] ?? null, 'primary');
        $secondary = $this->ramp($overrides['secondary'] ?? null, 'secondary');

        return match ($surface) {
            'tinted' => [
                '--surface-app' => $secondary[50],
                '--surface-nav' => '#ffffff',
                '--surface-nav-border' => $secondary[100],
                '--surface-nav-text' => '#334155',
                '--surface-nav-muted' => '#94a3b8',
                '--surface-nav-icon' => $secondary[500],
                '--surface-nav-hover' => $secondary[50],
                '--surface-nav-active' => $primary[50],
                '--surface-nav-active-text' => $primary[700],
                '--surface-nav-active-icon' => $primary[600],
                '--surface-topbar' => '#ffffff',
            ],
            'dark' => [
                '--surface-app' => '#f1f5f9',
                // Deep enough to read white text on, and tinted by the theme so
                // the column belongs to the same palette as everything else.
                '--surface-nav' => $this->mix($secondary[900], '#020617', 0.72),
                '--surface-nav-border' => $this->mix($secondary[900], '#020617', 0.5),
                '--surface-nav-text' => '#cbd5e1',
                '--surface-nav-muted' => '#64748b',
                '--surface-nav-icon' => '#94a3b8',
                '--surface-nav-hover' => $this->mix($secondary[800], '#020617', 0.55),
                '--surface-nav-active' => $primary[600],
                '--surface-nav-active-text' => $this->foregroundOn($primary[600]),
                '--surface-nav-active-icon' => $this->foregroundOn($primary[600]),
                '--surface-topbar' => '#ffffff',
            ],
            default => [
                '--surface-app' => '#f1f5f9',
                '--surface-nav' => '#ffffff',
                '--surface-nav-border' => '#e2e8f0',
                '--surface-nav-text' => '#475569',
                '--surface-nav-muted' => '#94a3b8',
                '--surface-nav-icon' => '#94a3b8',
                '--surface-nav-hover' => '#f1f5f9',
                '--surface-nav-active' => $primary[50],
                '--surface-nav-active-text' => $primary[700],
                '--surface-nav-active-icon' => $primary[600],
                '--surface-topbar' => '#ffffff',
            ],
        };
    }

    /**
     * Black or white, whichever stays readable on the colour. Used for text
     * sitting directly on a filled surface.
     */
    public function foregroundOn(?string $hex = null): string
    {
        return $this->luminance($this->isValid($hex) ? $hex : $this->color()) > 0.45
            ? '#0f172a'
            : '#ffffff';
    }

    /** Relative luminance, per WCAG. */
    public function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->toRgb($hex);

        $channel = function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /** The WCAG contrast ratio between two colours, 1 to 21. */
    public function contrast(string $a, string $b): float
    {
        $one = $this->luminance($a);
        $two = $this->luminance($b);

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }

    /**
     * The brand colour, darkened until it is safe to set text in.
     *
     * The 600 step is the colour an administrator chose, and it is chosen to
     * be *pressed* — it has to look right filling a button. Nothing says it is
     * readable as small text on a pale background, and for a yellow, a green
     * or a teal it plainly is not: teal on the page background measures
     * 3.4 to 1 where 4.5 is the floor, and yellow measures 1.4.
     *
     * So links do not use the 600. They use this: the same hue walked darker
     * until it clears the floor against the palest surface the shell paints,
     * and black if the hue can somehow never get there. The brand still shows
     * through — a teal link is recognisably teal — and it can still be read.
     */
    public function ink(?string $hex = null, string $role = 'primary'): string
    {
        $base = $this->isValid($hex) ? $hex : $this->color($role);

        // The page behind a card, which is darker than a card and therefore
        // the harder of the two surfaces a link has to sit on.
        $against = '#f1f5f9';

        if ($this->contrast($base, $against) >= self::TEXT_CONTRAST) {
            return strtolower($base);
        }

        [$r, $g, $b] = $this->toRgb($base);

        // Twenty steps of 5% towards black is enough to take any hue past the
        // floor well before it arrives, and stops at the first step that does
        // so rather than going darker than it has to.
        for ($step = 1; $step <= 20; $step++) {
            $candidate = $this->toHex(
                $this->blend($r, 0, $step * 0.05),
                $this->blend($g, 0, $step * 0.05),
                $this->blend($b, 0, $step * 0.05),
            );

            if ($this->contrast($candidate, $against) >= self::TEXT_CONTRAST) {
                return $candidate;
            }
        }

        return '#0f172a';
    }

    /** Two colours mixed, $amount being how far to travel towards the second. */
    public function mix(string $from, string $to, float $amount): string
    {
        [$r1, $g1, $b1] = $this->toRgb($from);
        [$r2, $g2, $b2] = $this->toRgb($to);

        return $this->toHex(
            $this->blend($r1, $r2, $amount),
            $this->blend($g1, $g2, $amount),
            $this->blend($b1, $b2, $amount),
        );
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function blend(int $from, int $to, float $amount): int
    {
        return (int) round($from + ($to - $from) * $amount);
    }

    private function toHex(int $r, int $g, int $b): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b)),
        );
    }
}
