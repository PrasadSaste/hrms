<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tab_icon_falls_back_to_the_company_initials(): void
    {
        $this->seedReferenceData();
        Setting::put('company_name', 'Beyond Sure', 'company');
        Setting::put('brand_color', '#123456', 'branding');

        $response = $this->get(route('branding.favicon'))->assertOk();

        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('#123456', $response->getContent());
        $this->assertStringContainsString('>BS<', $response->getContent());
    }

    public function test_an_uploaded_tab_icon_is_served_instead(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->settingsPayload([
                'company_favicon_file' => UploadedFile::fake()->image('icon.png', 64, 64),
            ]))
            ->assertRedirect();

        $stored = Setting::get('company_favicon');
        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);

        $response = $this->get(route('branding.favicon'))->assertOk();
        $this->assertStringContainsString('image/', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<svg', $response->getContent());
    }

    public function test_the_tab_icon_can_be_removed_again(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->put(route('settings.update'), $this->settingsPayload([
            'company_favicon_file' => UploadedFile::fake()->image('icon.png', 64, 64),
        ]));

        $stored = Setting::get('company_favicon');

        $this->actingAs($admin->user)->put(route('settings.update'), $this->settingsPayload([
            'remove_company_favicon' => '1',
        ]));

        $this->assertNull(Setting::get('company_favicon'));
        Storage::disk('public')->assertMissing($stored);
        $this->get(route('branding.favicon'))->assertOk();
    }

    public function test_an_oversized_tab_icon_is_refused(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->settingsPayload([
                'company_favicon_file' => UploadedFile::fake()->create('icon.png', 900, 'image/png'),
            ]))
            ->assertSessionHasErrors('company_favicon_file');

        $this->assertNull(Setting::get('company_favicon'));
    }

    public function test_the_sidebar_shows_the_logo_in_place_of_the_company_name(): void
    {
        Storage::fake('public');
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::put('company_name', 'Beyond Sure', 'company');

        // Before a logo is uploaded the name identifies the installation.
        $this->actingAs($admin->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Beyond Sure');

        $this->actingAs($admin->user)->put(route('settings.update'), $this->settingsPayload([
            'company_logo_file' => UploadedFile::fake()->image('logo.png', 320, 80),
        ]));

        $sidebar = $this->actingAs($admin->user)->get(route('dashboard'))->assertOk();

        // The logo now stands in for the name in the sidebar header.
        $sidebar->assertSee(route('branding.logo'));
        $this->assertStringNotContainsString(
            'truncate font-semibold',
            $sidebar->getContent(),
            'The company name is still rendered beside the logo.',
        );
    }

    /** The settings form posts every field, so a partial update is not a wipe. */
    protected function settingsPayload(array $overrides = []): array
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
