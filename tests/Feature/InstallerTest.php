<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\EnvironmentFile;
use App\Services\Installer;
use App\Support\Roles;
use App\Support\SystemRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Setting the system up on a server that has nothing on it.
 *
 * The suite otherwise runs as an installed system — phpunit.xml sets
 * INSTALL_LOCKED — so each test here says which state it wants, which is also
 * the clearest way to show that the switch does what it claims.
 */
class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected string $lock;

    protected function setUp(): void
    {
        parent::setUp();

        // A lock file of this test's own, so nothing touches the one belonging
        // to the machine the suite is running on.
        $this->lock = storage_path('framework/testing/installed-'.uniqid().'.json');

        config([
            'install.locked' => false,
            'install.lock_file' => $this->lock,
        ]);

        File::ensureDirectoryExists(dirname($this->lock));
    }

    protected function tearDown(): void
    {
        File::delete($this->lock);

        parent::tearDown();
    }

    protected function installer(): Installer
    {
        return $this->app->make(Installer::class);
    }

    /** Somebody who can sign in and administer everything. */
    protected function administrator(): User
    {
        $this->seedReferenceData();

        $admin = User::create([
            'name' => 'Existing Administrator',
            'email' => 'boss@example.test',
            'password' => 'Password123!',
            'status' => 'active',
        ]);

        $admin->syncRoles([Roles::SUPER_ADMIN]);

        return $admin;
    }

    /*
    |--------------------------------------------------------------------------
    | Is it installed
    |--------------------------------------------------------------------------
    */

    public function test_a_system_with_no_administrator_and_no_lock_file_is_not_installed(): void
    {
        $this->assertFalse($this->installer()->installed());
    }

    public function test_the_lock_file_is_what_says_a_system_is_installed(): void
    {
        $this->installer()->lock(['administrator' => 'someone@example.test']);

        $this->assertTrue($this->installer()->installed());
        $this->assertSame('someone@example.test', $this->installer()->details()['administrator']);
    }

    public function test_an_installation_that_predates_the_wizard_is_recognised_and_locked(): void
    {
        // No lock file, but there is plainly somebody running this system: an
        // upgrade must not drop them back onto a setup screen.
        $this->administrator();

        $this->assertTrue($this->installer()->installed());
        $this->assertFileExists($this->lock, 'The recognition is written down, so it is only worked out once.');
    }

    public function test_the_environment_switch_closes_the_installer_on_its_own(): void
    {
        config(['install.locked' => true]);

        $this->assertTrue($this->installer()->installed());

        $this->get('/install')->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | Who may see what
    |--------------------------------------------------------------------------
    */

    public function test_every_page_leads_to_the_installer_until_it_is_finished(): void
    {
        foreach (['/dashboard', '/login', '/employees', '/settings'] as $page) {
            $this->get($page)->assertRedirect(route('install.index'));
        }
    }

    public function test_the_api_says_so_rather_than_redirecting_a_machine_to_a_form(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(503)
            ->assertJsonFragment(['message' => 'This system has not been set up yet. Open it in a browser to finish installing it.']);
    }

    public function test_the_installer_is_shut_the_moment_it_is_finished(): void
    {
        $this->installer()->lock();

        foreach ([
            '/install',
            '/install/database',
            '/install/organisation',
            '/install/administrator',
        ] as $page) {
            $this->get($page)->assertRedirect(route('login'));
        }

        $this->post('/install/administrator', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'password' => 'correct-horse-battery-9',
            'password_confirmation' => 'correct-horse-battery-9',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
    }

    public function test_the_finish_page_needs_the_signed_link_it_was_given(): void
    {
        $this->installer()->lock();

        $this->get('/install/complete?admin=someone@example.test')->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | The steps
    |--------------------------------------------------------------------------
    */

    public function test_the_first_screen_reports_what_this_server_is_missing(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('Can this server run it?')
            ->assertSee('pdo_mysql');
    }

    public function test_the_wizard_sends_you_to_the_step_that_is_actually_due(): void
    {
        // The schema is here (the test database is migrated) but nobody has
        // answered anything, so the organisation is what is outstanding.
        $this->get('/install/administrator')->assertRedirect(route('install.organisation'));
    }

    public function test_the_organisation_step_writes_the_settings_and_the_first_company(): void
    {
        $this->post('/install/organisation', $this->organisation())
            ->assertRedirect(route('install.administrator'));

        $this->assertSame('Northwind Logistics', Setting::get('company_name'));
        $this->assertSame('Asia/Kolkata', Setting::get('timezone'));
        $this->assertSame('#123456', Setting::get('brand_color'));
        $this->assertSame('Northwind Logistics HRMS', Setting::get('mail_from_name'));

        $company = Company::where('code', 'NL')->firstOrFail();
        $this->assertSame('Northwind Logistics Private Limited', $company->legal_name);
        $this->assertTrue($company->is_default, 'The only company is the default one.');
        $this->assertSame('NL', $company->payslip_prefix);
    }

    public function test_it_leaves_a_head_office_and_a_shift_so_a_person_can_be_added(): void
    {
        $this->post('/install/organisation', $this->organisation());

        $branch = Branch::where('code', 'HO')->firstOrFail();
        $this->assertSame('Pune Head Office', $branch->name);
        $this->assertTrue($branch->is_head_office);

        $shift = Shift::where('code', 'GEN')->firstOrFail();
        $this->assertTrue($shift->is_default);
        $this->assertSame(
            [1, 2, 3, 4, 5, 6],
            $shift->working_days,
            'Saturday is left to the branch to decide, as it is on the seeded shifts.',
        );
    }

    public function test_the_placeholder_company_the_migration_leaves_is_absorbed(): void
    {
        // The multi-company migration creates one entity from the settings as
        // they stood. On a fresh installation that is this company, misnamed.
        $this->assertDatabaseHas('companies', ['code' => Company::PLACEHOLDER_CODE]);

        $this->post('/install/organisation', $this->organisation());

        $this->assertDatabaseMissing('companies', ['code' => Company::PLACEHOLDER_CODE]);
        $this->assertSame(1, Company::count(), 'One company, the one that was asked for.');
    }

    public function test_the_administrator_step_creates_the_account_and_closes_setup(): void
    {
        // By the time this step is reached the database step has run the
        // framework seeder, so the roles exist. RefreshDatabase migrates but
        // does not seed, so the precondition is set up here instead.
        $this->seedReferenceData();

        $this->post('/install/organisation', $this->organisation());

        $response = $this->post('/install/administrator', [
            'name' => 'Sanjay Rao',
            'email' => 'Sanjay@Northwind.Example',
            'password' => 'thistle-lantern-4492',
            'password_confirmation' => 'thistle-lantern-4492',
        ]);

        $response->assertRedirectContains('/install/complete');

        $admin = User::where('email', 'sanjay@northwind.example')->firstOrFail();
        $this->assertTrue($admin->hasRole(Roles::SUPER_ADMIN));
        $this->assertFalse($admin->must_change_password, 'They just chose it.');

        $this->assertTrue($this->installer()->installed());
        $this->assertSame('sanjay@northwind.example', $this->installer()->details()['administrator']);

        // And the finish page opens on the signed link it was handed.
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Northwind Logistics is ready.');
    }

    public function test_a_weak_or_mistyped_password_is_refused(): void
    {
        $this->post('/install/organisation', $this->organisation());

        $this->post('/install/administrator', [
            'name' => 'Sanjay Rao',
            'email' => 'sanjay@northwind.example',
            'password' => 'thistle-lantern-4492',
            'password_confirmation' => 'thistle-lantern-4493',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'sanjay@northwind.example']);
        $this->assertFalse($this->installer()->installed());
    }

    /*
    |--------------------------------------------------------------------------
    | The database step
    |--------------------------------------------------------------------------
    */

    public function test_a_connection_that_cannot_be_opened_is_explained_in_words(): void
    {
        $error = $this->installer()->testConnection([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'nothing_here',
            'username' => 'nobody',
            'password' => 'wrong',
        ]);

        $this->assertNotNull($error);
        $this->assertStringNotContainsString('SQLSTATE', $error, 'A driver code helps nobody filling in a form.');
        $this->assertStringContainsString('Nothing is listening', $error);
        $this->assertStringContainsString(
            'never checked',
            $error,
            'Saying the credentials were not reached stops somebody re-checking a password that was never the problem.',
        );
    }

    /**
     * The words a driver uses are the operating system's, and translated.
     *
     * Windows does not say "connection refused", it says a machine "actively
     * refused" — and a localised server says neither. Matching on the English
     * meant a Windows installer got a raw SQLSTATE dump instead of an answer,
     * so the number in the brackets is what decides now.
     *
     * @param  class-string|string  $_
     */
    #[DataProvider('driverFailures')]
    public function test_a_failure_is_explained_from_its_code_not_its_wording(string $message, string $expected): void
    {
        $explain = new ReflectionMethod(Installer::class, 'explain');

        $answer = $explain->invoke($this->installer(), new PDOException($message), [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'hrms_bysure',
        ]);

        $this->assertStringContainsString($expected, $answer);
        $this->assertStringNotContainsString('SQLSTATE', $answer);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function driverFailures(): array
    {
        return [
            'windows refuses the port' => [
                'SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it',
                'Nothing is listening on 127.0.0.1:3306',
            ],
            'linux refuses the port' => [
                'SQLSTATE[HY000] [2002] Connection refused',
                'Nothing is listening on 127.0.0.1:3306',
            ],
            'a server answering in another language' => [
                'SQLSTATE[HY000] [2002] Verbindungsaufbau abgelehnt',
                'Nothing is listening on 127.0.0.1:3306',
            ],
            'wrong password' => [
                "SQLSTATE[28000] [1045] Access denied for user 'hrms'@'localhost' (using password: YES)",
                'refused the username or password',
            ],
            'no such database' => [
                "SQLSTATE[HY000] [1049] Unknown database 'hrms_bysure'",
                'does not exist',
            ],
            'no such host' => [
                "SQLSTATE[HY000] [2005] Unknown MySQL server host 'db.wrong'",
                'could not be found',
            ],
            'no pdo driver at all' => [
                'could not find driver',
                'Install the matching PDO extension',
            ],
        ];
    }

    public function test_a_sqlite_file_is_created_and_opened(): void
    {
        $path = storage_path('framework/testing/trial-'.uniqid().'.sqlite');

        $this->assertNull($this->installer()->testConnection([
            'driver' => 'sqlite',
            'database' => $path,
        ]));

        $this->assertFileExists($path);

        File::delete($path);
    }

    /*
    |--------------------------------------------------------------------------
    | The pieces
    |--------------------------------------------------------------------------
    */

    public function test_a_company_code_is_made_from_the_name(): void
    {
        $installer = $this->installer();

        $this->assertSame('NL', $installer->companyCode('Northwind Logistics'));
        $this->assertSame('BSPL', $installer->companyCode('Beyond Sure Private Limited'));
        $this->assertSame('ACME', $installer->companyCode('Acme'));
        $this->assertSame('CO', $installer->companyCode('###'), 'Something unusable still gets a code.');
    }

    public function test_the_requirements_separate_what_is_needed_from_what_is_merely_nice(): void
    {
        $requirements = new SystemRequirements;

        $names = array_column($requirements->required(), 'name');

        $this->assertContains('pdo_mysql', $names);
        $this->assertNotContains('gd', $names, 'A logo format nobody may use must not block an installation.');

        // Whatever this machine happens to have, an optional extension can
        // never be the reason setup refuses to go ahead.
        foreach ($requirements->failures() as $failure) {
            $this->assertTrue($failure['required']);
        }
    }

    public function test_the_environment_file_is_edited_rather_than_rewritten(): void
    {
        $path = storage_path('framework/testing/env-'.uniqid());
        file_put_contents($path, "APP_NAME=Old\n# a comment worth keeping\n\nDB_HOST=localhost\n");

        $file = new EnvironmentFile($path);
        $file->write(['DB_HOST' => 'db.internal', 'DB_PASSWORD' => 'p@ss w0rd#$x', 'NEW_KEY' => 'added']);

        $written = file_get_contents($path);

        $this->assertStringContainsString('# a comment worth keeping', $written);
        $this->assertStringContainsString('APP_NAME=Old', $written);
        $this->assertSame('db.internal', $file->get('DB_HOST'));
        $this->assertSame('p@ss w0rd#$x', $file->get('DB_PASSWORD'), 'Quoted, so a # does not start a comment.');
        $this->assertSame('added', $file->get('NEW_KEY'));

        File::delete($path);
    }

    /** @return array<string, string> */
    protected function organisation(): array
    {
        return [
            'company_name' => 'Northwind Logistics',
            'legal_name' => 'Northwind Logistics Private Limited',
            'company_email' => 'hr@northwind.example',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'country' => 'India',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'brand_color' => '#123456',
            'brand_secondary_color' => '#0d9488',
            'brand_tertiary_color' => '#f59e0b',
        ];
    }
}
