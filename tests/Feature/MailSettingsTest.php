<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MailSettings;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setting the mail server from the interface instead of the environment file.
 */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected MailSettings $mail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mail = app(MailSettings::class);
    }

    // ------------------------------------------------------ what is in force

    public function test_nothing_is_overridden_until_somebody_chooses_a_transport(): void
    {
        config(['mail.default' => 'array', 'mail.mailers.smtp.host' => 'from-env.example']);

        $this->mail->apply();

        $this->assertFalse($this->mail->overridesEnvironment());
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('from-env.example', config('mail.mailers.smtp.host'));
    }

    public function test_a_chosen_smtp_server_wins_over_the_environment(): void
    {
        config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'from-env.example']);

        Setting::put('mail_transport', 'smtp', 'mail');
        Setting::put('mail_host', 'smtp.shrigodatechlabs.com', 'mail');
        Setting::put('mail_port', '587', 'mail');
        Setting::put('mail_username', 'hrms', 'mail');
        Setting::put('mail_scheme', 'smtp', 'mail');
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        $this->mail->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.shrigodatechlabs.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('hrms', config('mail.mailers.smtp.username'));
        $this->assertSame('super-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    public function test_an_empty_encryption_means_decide_from_the_port(): void
    {
        Setting::put('mail_transport', 'smtp', 'mail');
        Setting::put('mail_host', 'smtp.example.com', 'mail');
        Setting::put('mail_scheme', '', 'mail');

        $this->mail->apply();

        // Not an empty string: that would be sent as a scheme and refused.
        $this->assertNull(config('mail.mailers.smtp.scheme'));
    }

    public function test_choosing_the_log_sends_nothing_anywhere(): void
    {
        config(['mail.default' => 'smtp']);
        Setting::put('mail_transport', 'log', 'mail');

        $this->mail->apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_a_transport_that_is_not_offered_is_ignored(): void
    {
        Setting::put('mail_transport', 'carrier-pigeon', 'mail');

        $this->assertSame(MailSettings::FROM_ENV, $this->mail->transport());
        $this->assertFalse($this->mail->overridesEnvironment());
    }

    // -------------------------------------------------------- the password

    public function test_the_password_is_encrypted_in_its_row(): void
    {
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        $stored = DB::table('settings')->where('key', MailSettings::PASSWORD_KEY)->value('value');

        $this->assertNotSame('super-secret', $stored);
        $this->assertStringNotContainsString('super-secret', (string) $stored);
        $this->assertSame('super-secret', Setting::secret(MailSettings::PASSWORD_KEY));
    }

    public function test_the_password_never_reaches_the_shared_cache(): void
    {
        // The cache store is the database, so a decrypted value sitting in it
        // would undo the encryption entirely.
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        $this->assertArrayNotHasKey(MailSettings::PASSWORD_KEY, Setting::allValues());
        $this->assertNull(Setting::get(MailSettings::PASSWORD_KEY));
    }

    public function test_a_password_that_cannot_be_decrypted_reads_as_none(): void
    {
        // A changed APP_KEY leaves rows nobody can read. A mailer without a
        // password is a better outcome than an exception on every request.
        DB::table('settings')->insert([
            'key' => MailSettings::PASSWORD_KEY,
            'value' => 'not-actually-encrypted',
            'group' => 'mail',
            'type' => Setting::SECRET,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(Setting::secret(MailSettings::PASSWORD_KEY));
    }

    // ---------------------------------------------------------- the screen

    public function test_an_administrator_can_set_an_smtp_server(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->payload([
                'mail_transport' => 'smtp',
                'mail_host' => 'smtp.shrigodatechlabs.com',
                'mail_port' => 587,
                'mail_username' => 'hrms',
                'mail_password' => 'super-secret',
                'mail_scheme' => 'smtp',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('smtp', Setting::get('mail_transport'));
        $this->assertSame('smtp.shrigodatechlabs.com', Setting::get('mail_host'));
        $this->assertSame('super-secret', Setting::secret(MailSettings::PASSWORD_KEY));
    }

    public function test_an_empty_password_keeps_the_one_already_saved(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        // Changing the host must not force somebody to retype the password,
        // which is never rendered back into the form to begin with.
        $this->actingAs($admin->user)->put(route('settings.update'), $this->payload([
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.new-provider.com',
            'mail_port' => 587,
            'mail_password' => '',
        ]))->assertRedirect();

        $this->assertSame('super-secret', Setting::secret(MailSettings::PASSWORD_KEY));
        $this->assertSame('smtp.new-provider.com', Setting::get('mail_host'));
    }

    public function test_the_password_can_be_removed_deliberately(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        $this->actingAs($admin->user)->put(route('settings.update'), $this->payload([
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'clear_mail_password' => '1',
        ]))->assertRedirect();

        $this->assertNull(Setting::secret(MailSettings::PASSWORD_KEY));
    }

    public function test_leaving_smtp_drops_the_password(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        // A password kept for a server nobody sends through is only a risk.
        $this->actingAs($admin->user)->put(route('settings.update'), $this->payload([
            'mail_transport' => 'log',
        ]))->assertRedirect();

        $this->assertNull(Setting::secret(MailSettings::PASSWORD_KEY));
    }

    public function test_smtp_needs_a_host_and_a_port(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->payload(['mail_transport' => 'smtp']))
            ->assertSessionHasErrors(['mail_host', 'mail_port']);
    }

    public function test_the_other_choices_need_no_server(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->put(route('settings.update'), $this->payload(['mail_transport' => 'log']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_a_request_without_the_mail_block_leaves_it_alone(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::put('mail_transport', 'smtp', 'mail');
        Setting::put('mail_host', 'smtp.example.com', 'mail');
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        $this->actingAs($admin->user)->put(route('settings.update'), $this->payload())
            ->assertRedirect();

        $this->assertSame('smtp', Setting::get('mail_transport'));
        $this->assertSame('super-secret', Setting::secret(MailSettings::PASSWORD_KEY));
    }

    public function test_the_screen_shows_the_server_but_never_the_password(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        Setting::put('mail_transport', 'smtp', 'mail');
        Setting::put('mail_host', 'smtp.shrigodatechlabs.com', 'mail');
        Setting::putSecret(MailSettings::PASSWORD_KEY, 'super-secret', 'mail');

        $this->actingAs($admin->user)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('smtp.shrigodatechlabs.com')
            ->assertSee('Send mail through')
            ->assertDontSee('super-secret');
    }

    public function test_an_ordinary_employee_cannot_change_the_mail_server(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)
            ->put(route('settings.update'), $this->payload(['mail_transport' => 'smtp']))
            ->assertForbidden();

        $this->assertNull(Setting::get('mail_transport'));
    }

    // ------------------------------------------------------------ the test

    public function test_a_test_message_is_sent_and_reported(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        config(['mail.redirect.to' => null]);

        $this->actingAs($admin->user)
            ->post(route('settings.test-email'), ['test_email' => 'ops@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'ops@example.com'));
    }

    public function test_a_test_message_says_when_it_was_diverted_instead(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);
        config(['mail.redirect.to' => 'api@shrigodatechlabs.com']);

        $this->actingAs($admin->user)
            ->post(route('settings.test-email'), ['test_email' => 'ops@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'api@shrigodatechlabs.com'));
    }

    public function test_a_failing_mail_server_is_reported_rather_than_swallowed(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        // Somewhere nothing is listening: the point of a test is to hear that.
        Setting::put('mail_transport', 'smtp', 'mail');
        Setting::put('mail_host', '127.0.0.1', 'mail');
        Setting::put('mail_port', '1', 'mail');

        $this->actingAs($admin->user)
            ->post(route('settings.test-email'), ['test_email' => 'ops@example.com'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_the_test_needs_an_address(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)
            ->post(route('settings.test-email'), ['test_email' => 'not-an-address'])
            ->assertSessionHasErrors('test_email');
    }

    /** The settings form posts every field, so a partial update is not a wipe. */
    protected function payload(array $overrides = []): array
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
