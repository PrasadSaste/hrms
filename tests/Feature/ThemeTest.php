<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\BrandPalette;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three theme colours and the surface the interface sits on.
 */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected BrandPalette $palette;

    protected function setUp(): void
    {
        parent::setUp();
        $this->palette = app(BrandPalette::class);
    }

    // ------------------------------------------------------- three colours

    public function test_each_role_falls_back_to_its_own_default(): void
    {
        $this->assertSame(BrandPalette::DEFAULT, $this->palette->color('primary'));
        $this->assertSame(BrandPalette::DEFAULT_SECONDARY, $this->palette->color('secondary'));
        $this->assertSame(BrandPalette::DEFAULT_TERTIARY, $this->palette->color('tertiary'));
    }

    public function test_each_role_reads_its_own_setting(): void
    {
        Setting::put('brand_color', '#123456', 'branding');
        Setting::put('brand_secondary_color', '#654321', 'branding');
        Setting::put('brand_tertiary_color', '#abcdef', 'branding');

        $this->assertSame([
            'primary' => '#123456',
            'secondary' => '#654321',
            'tertiary' => '#abcdef',
        ], $this->palette->colors());
    }

    public function test_a_nonsense_colour_is_ignored_rather_than_printed(): void
    {
        Setting::put('brand_secondary_color', 'rgb(1,2,3)', 'branding');

        $this->assertSame(BrandPalette::DEFAULT_SECONDARY, $this->palette->color('secondary'));
    }

    public function test_each_colour_gets_its_own_ten_shades(): void
    {
        Setting::put('brand_secondary_color', '#0d9488', 'branding');

        $ramp = $this->palette->ramp(null, 'secondary');

        $this->assertCount(10, $ramp);
        // 600 is the colour itself; the rest run from near-white to near-black.
        $this->assertSame('#0d9488', $ramp[600]);
        $this->assertNotSame($ramp[50], $ramp[900]);
        $this->assertGreaterThan(
            hexdec(ltrim($ramp[900], '#')),
            hexdec(ltrim($ramp[50], '#')),
            'The lightest shade should be lighter than the darkest.',
        );
    }

    public function test_all_three_ramps_reach_the_page(): void
    {
        Setting::put('brand_color', '#123456', 'branding');
        Setting::put('brand_secondary_color', '#654321', 'branding');
        Setting::put('brand_tertiary_color', '#abcdef', 'branding');

        $css = $this->palette->cssVariables();

        $this->assertStringContainsString('--color-brand-600:#123456', $css);
        $this->assertStringContainsString('--color-accent-600:#654321', $css);
        $this->assertStringContainsString('--color-tertiary-600:#abcdef', $css);
        $this->assertStringContainsString('--surface-nav:', $css);
    }

    public function test_text_on_a_filled_surface_stays_readable(): void
    {
        // White on a dark colour, near-black on a light one.
        $this->assertSame('#ffffff', $this->palette->foregroundOn('#1e293b'));
        $this->assertSame('#0f172a', $this->palette->foregroundOn('#fde68a'));
    }

    // ------------------------------------------------------- the surfaces

    /**
     * The 600 step is chosen to be pressed, not to be read.
     *
     * Filling a button with a teal is fine; setting twelve-pixel link text in
     * the same teal is not — it measures 3.4 to 1 against the page, where 4.5
     * is the floor. `ink()` is the shade links actually use.
     */
    public function test_the_link_shade_is_readable_whatever_colour_is_chosen(): void
    {
        // Every one of these fails as text at the 600 step.
        foreach (['#0d9488', '#22c55e', '#f59e0b', '#facc15', '#38bdf8'] as $hex) {
            $this->assertLessThan(
                BrandPalette::TEXT_CONTRAST,
                $this->palette->contrast($hex, '#f1f5f9'),
                $hex.' was expected to fail as text at the 600 step.',
            );

            $this->assertGreaterThanOrEqual(
                BrandPalette::TEXT_CONTRAST,
                $this->palette->contrast($this->palette->ink($hex), '#f1f5f9'),
                $hex.' still fails once darkened to its ink.',
            );
        }
    }

    public function test_a_colour_that_already_reads_is_left_alone(): void
    {
        // A dark navy needs no help, and darkening it further would only take
        // the brand out of the link for nothing.
        foreach (['#1e3a8a', '#2563eb', '#0b1f6b'] as $hex) {
            $this->assertSame($hex, $this->palette->ink($hex));
        }
    }

    public function test_the_link_shade_reaches_the_page(): void
    {
        Setting::put('brand_color', '#facc15', 'branding');

        $css = $this->palette->cssVariables();

        $this->assertStringContainsString('--color-brand-ink:', $css);
        $this->assertStringContainsString('--color-accent-ink:', $css);
        $this->assertStringContainsString('--color-tertiary-ink:', $css);
        $this->assertStringNotContainsString('--color-brand-ink:#facc15', $css);
    }

    public function test_the_sign_in_screen_underlines_its_recovery_link(): void
    {
        // Colour is never the only signal: a dark brand clears contrast and
        // then sits a fifth of a stop from the label beside it.
        Setting::put('brand_color', '#1e3a8a', 'branding');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee('class="link text-[12.5px] font-medium"', escape: false);
    }

    public function test_the_light_surface_leaves_the_shell_neutral(): void
    {
        $vars = $this->palette->surfaceVariables('light');

        $this->assertSame('#ffffff', $vars['--surface-nav']);
        $this->assertSame('#f1f5f9', $vars['--surface-app']);
    }

    public function test_the_tinted_surface_washes_the_page_with_the_secondary_colour(): void
    {
        Setting::put('brand_secondary_color', '#0d9488', 'branding');

        $vars = $this->palette->surfaceVariables('tinted');
        $secondary = $this->palette->ramp(null, 'secondary');

        $this->assertSame($secondary[50], $vars['--surface-app']);
        $this->assertSame($secondary[500], $vars['--surface-nav-icon']);
    }

    public function test_the_dark_surface_inverts_the_navigation_and_keeps_its_text_readable(): void
    {
        $vars = $this->palette->surfaceVariables('dark');

        // Dark enough that the light text sitting on it is legible.
        $this->assertSame('#ffffff', $this->palette->foregroundOn($vars['--surface-nav']));
        $this->assertSame(
            $this->palette->foregroundOn($this->palette->ramp(null, 'primary')[600]),
            $vars['--surface-nav-active-text'],
        );
    }

    public function test_an_unknown_surface_falls_back_to_light(): void
    {
        Setting::put('theme_surface', 'neon', 'branding');

        $this->assertSame('light', $this->palette->surface());
    }

    // -------------------------------------------------------- the screens

    public function test_the_theme_is_written_into_every_page(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::put('brand_color', '#123456', 'branding');
        Setting::put('theme_surface', 'dark', 'branding');

        $this->actingAs($admin->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--color-brand-600:#123456', false)
            ->assertSee('--color-accent-600:', false)
            ->assertSee('--color-tertiary-600:', false)
            ->assertSee('--surface-nav:', false);
    }

    public function test_an_administrator_can_set_all_three_colours(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->themePayload([
                'brand_color' => '#7C3AED',
                'brand_secondary_color' => '#0891B2',
                'brand_tertiary_color' => '#E11D48',
                'theme_surface' => 'dark',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Stored in lower case, so a hex typed either way compares equal.
        $this->assertSame('#7c3aed', Setting::get('brand_color'));
        $this->assertSame('#0891b2', Setting::get('brand_secondary_color'));
        $this->assertSame('#e11d48', Setting::get('brand_tertiary_color'));
        $this->assertSame('dark', Setting::get('theme_surface'));
    }

    public function test_a_colour_that_is_not_a_hex_is_refused(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->themePayload(['brand_secondary_color' => 'teal']))
            ->assertSessionHasErrors('brand_secondary_color');
    }

    public function test_a_background_that_does_not_exist_is_refused(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->themePayload(['theme_surface' => 'neon']))
            ->assertSessionHasErrors('theme_surface');
    }

    public function test_the_settings_screen_offers_all_three_pickers(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('name="brand_color"', false)
            ->assertSee('name="brand_secondary_color"', false)
            ->assertSee('name="brand_tertiary_color"', false)
            ->assertSee('name="theme_surface"', false)
            ->assertSee('Layout preview');
    }

    public function test_the_tab_icon_carries_both_the_primary_and_secondary_colour(): void
    {
        $this->seedReferenceData();
        Setting::put('company_name', 'Beyond Sure', 'company');
        Setting::put('brand_color', '#123456', 'branding');
        Setting::put('brand_secondary_color', '#654321', 'branding');

        $icon = $this->get(route('branding.favicon'))->assertOk()->getContent();

        $this->assertStringContainsString('#123456', $icon);
        $this->assertStringContainsString('#654321', $icon);
    }

    /** The settings form posts every field, so a partial update is not a wipe. */
    protected function themePayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Beyond Sure',
            'brand_color' => '#2563eb',
            'brand_secondary_color' => '#0d9488',
            'brand_tertiary_color' => '#f59e0b',
            'theme_surface' => 'light',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd M Y',
            'employee_code_prefix' => 'EMP',
            'employee_code_padding' => 4,
            'financial_year_start_month' => 4,
        ], $overrides);
    }
}
