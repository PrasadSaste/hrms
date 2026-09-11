<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\FrameworkSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Setting the system up on a server that has nothing on it yet.
 *
 * The wizard is four questions long — is this machine capable, where is the
 * database, who is the company, who is the administrator — and each one is
 * answered here rather than in the controller, so `php artisan hrms:install`
 * asks exactly the same questions and reaches exactly the same state.
 *
 * Every step is durable and repeatable: the database credentials are written
 * to `.env` the moment they are proved to work, the schema is migrated before
 * the organisation is asked for, and the stage reached is remembered in the
 * settings table. Closing the browser half way through loses nothing, and
 * running a step twice writes the same rows twice rather than two sets.
 *
 * When it finishes it writes a lock file, and the presence of that file is
 * what shuts the installer. It is a file rather than a row because a restored
 * backup or a swapped database must never be able to reopen setup on a server
 * that is already carrying somebody's payroll.
 */
class Installer
{
    public const STAGE_DATABASE = 'database';

    public const STAGE_ORGANISATION = 'organisation';

    public const STAGE_ADMINISTRATOR = 'administrator';

    /** The order the stages are completed in. */
    public const STAGES = [self::STAGE_DATABASE, self::STAGE_ORGANISATION, self::STAGE_ADMINISTRATOR];

    protected ?bool $administratorExists = null;

    public function __construct(protected EnvironmentFile $environment) {}

    /*
    |--------------------------------------------------------------------------
    | Is this thing installed?
    |--------------------------------------------------------------------------
    */

    public function installed(): bool
    {
        if (config('install.locked')) {
            return true;
        }

        if ($this->locked()) {
            return true;
        }

        /*
         * An installation that predates the wizard has no lock file and is
         * plainly finished — it has somebody who can sign in as an
         * administrator. Recognising that is what stops an upgrade from
         * dropping a live system back onto a setup screen; the lock file is
         * written on the spot so the question is only ever asked once.
         */
        if ($this->hasAdministrator()) {
            $this->lock(['detected' => 'An existing installation, recognised on upgrade.']);

            return true;
        }

        return false;
    }

    public function locked(): bool
    {
        return is_file($this->lockPath());
    }

    public function lockPath(): string
    {
        return (string) config('install.lock_file', storage_path('installed.json'));
    }

    /** @param array<string, mixed> $details */
    public function lock(array $details = []): void
    {
        $path = $this->lockPath();

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        @file_put_contents($path, json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => $this->version(),
        ] + $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /** Reopen the installer. Only the command line can do this. */
    public function unlock(): void
    {
        if ($this->locked()) {
            @unlink($this->lockPath());
        }

        $this->administratorExists = null;
    }

    /** @return array<string, mixed>|null */
    public function details(): ?array
    {
        if (! $this->locked()) {
            return null;
        }

        return json_decode((string) file_get_contents($this->lockPath()), true) ?: null;
    }

    protected function version(): string
    {
        return trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unversioned';
    }

    /**
     * Somebody can already sign in and administer this system.
     *
     * Answered once per request: it runs on a machine where the database may
     * not exist, so failing to reach it means "no", never an error page.
     */
    public function hasAdministrator(): bool
    {
        if ($this->administratorExists !== null) {
            return $this->administratorExists;
        }

        try {
            $this->administratorExists = Schema::hasTable('users')
                && Schema::hasTable('roles')
                && User::role(Roles::SUPER_ADMIN)->exists();
        } catch (Throwable) {
            $this->administratorExists = false;
        }

        return $this->administratorExists;
    }

    /*
    |--------------------------------------------------------------------------
    | How far through
    |--------------------------------------------------------------------------
    */

    /** The stage the wizard should be showing, given what is already done. */
    public function stage(): string
    {
        if (! $this->schemaReady()) {
            return self::STAGE_DATABASE;
        }

        if ($this->hasAdministrator()) {
            return self::STAGE_ADMINISTRATOR;
        }

        $reached = (string) $this->rememberedStage();

        return match ($reached) {
            self::STAGE_ORGANISATION => self::STAGE_ADMINISTRATOR,
            self::STAGE_DATABASE => self::STAGE_ORGANISATION,
            default => self::STAGE_ORGANISATION,
        };
    }

    public function completed(string $stage): bool
    {
        $reached = array_search((string) $this->rememberedStage(), self::STAGES, true);
        $asked = array_search($stage, self::STAGES, true);

        return $reached !== false && $asked !== false && $reached >= $asked;
    }

    protected function rememberedStage(): ?string
    {
        try {
            return Schema::hasTable('settings') ? Setting::get('install_stage') : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function recordStage(string $stage): void
    {
        Setting::put('install_stage', $stage, 'system');
    }

    /** The schema is in place, so the wizard can write to it. */
    public function schemaReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Step one: the database
    |--------------------------------------------------------------------------
    */

    /**
     * Open the connection described, without disturbing the one in use.
     *
     * Returns null when it worked and the reason in plain words when it did
     * not, because "SQLSTATE[HY000] [1045]" tells the person filling in the
     * form nothing they can act on.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function testConnection(array $credentials): ?string
    {
        try {
            $this->probe($credentials, $credentials['database'] ?? null);

            return null;
        } catch (Throwable $e) {
            // The one failure worth trying to fix rather than report: the
            // server is there and the login works, the schema just has not
            // been created yet. Create it and try again.
            if ($this->missingDatabase($e) && ($credentials['driver'] ?? 'mysql') !== 'sqlite') {
                try {
                    $this->createDatabase($credentials);
                    $this->probe($credentials, $credentials['database'] ?? null);

                    return null;
                } catch (Throwable $second) {
                    return $this->explain($second, $credentials);
                }
            }

            return $this->explain($e, $credentials);
        }
    }

    /**
     * Prove the credentials work and then keep them: written to `.env` so the
     * next request uses them, and applied to the running process so this one
     * can migrate straight away.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function useDatabase(array $credentials): void
    {
        if ($error = $this->testConnection($credentials)) {
            throw new RuntimeException($error);
        }

        $driver = (string) ($credentials['driver'] ?? 'mysql');

        $this->environment->write($driver === 'sqlite'
            ? [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => (string) $credentials['database'],
            ]
            : [
                'DB_CONNECTION' => $driver,
                'DB_HOST' => (string) $credentials['host'],
                'DB_PORT' => (string) $credentials['port'],
                'DB_DATABASE' => (string) $credentials['database'],
                'DB_USERNAME' => (string) ($credentials['username'] ?? ''),
                'DB_PASSWORD' => (string) ($credentials['password'] ?? ''),
            ]);

        $this->applyConnection($credentials);
        $this->forgetCachedConfiguration();
    }

    /**
     * Point the default connection at these credentials for the rest of the
     * request, so migrations run against the database just chosen rather than
     * whatever `.env` said when the process started.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function applyConnection(array $credentials): void
    {
        $driver = (string) ($credentials['driver'] ?? 'mysql');

        config([
            'database.default' => $driver,
            'database.connections.'.$driver => $this->connectionConfig($credentials),
        ]);

        DB::purge($driver);
        DB::reconnect($driver);
        DB::setDefaultConnection($driver);
    }

    /**
     * Bring the schema up to date and lay down the reference data.
     *
     * Both are safe to run again: migrations skip what has already run and the
     * seeders write with updateOrCreate, so a reload half way through setup
     * repeats the work rather than duplicating it.
     */
    public function migrate(): void
    {
        @set_time_limit(0);

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => FrameworkSeeder::class, '--force' => true]);

        $this->administratorExists = null;
        $this->recordStage(self::STAGE_DATABASE);
    }

    /*
    |--------------------------------------------------------------------------
    | Step two: who the company is
    |--------------------------------------------------------------------------
    */

    /**
     * The organisation's own answers, written over the seeded defaults.
     *
     * As well as the settings this creates the first payroll entity, its head
     * office and a general shift, because an HRMS with no company, no branch
     * and no shift cannot have an employee added to it — the first thing
     * anybody wants to do after signing in.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOrganisation(array $data): Company
    {
        $name = trim((string) $data['company_name']);

        foreach ([
            'company_name' => $name,
            'company_email' => $data['company_email'] ?? null,
            'company_phone' => $data['company_phone'] ?? null,
            'company_website' => $data['company_website'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'company_tax_id' => $data['company_tax_id'] ?? null,
        ] as $key => $value) {
            Setting::put($key, $value === '' ? null : $value, 'company');
        }

        foreach ([
            'currency' => $data['currency'] ?? 'INR',
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
        ] as $key => $value) {
            Setting::put($key, $value, 'general');
        }

        if ($logo = ($data['company_logo'] ?? null)) {
            Setting::put('company_logo', $logo, 'branding');
        }

        foreach (['brand_color', 'brand_secondary_color', 'brand_tertiary_color'] as $key) {
            if (! empty($data[$key])) {
                Setting::put($key, $data[$key], 'branding');
            }
        }

        Setting::put('mail_from_name', $name.' HRMS', 'mail');

        if ($from = ($data['company_email'] ?? null)) {
            Setting::put('mail_from_address', $from, 'mail');
        }

        $company = $this->firstCompany($data, $name, $logo ?? null);

        $this->headOffice($data, $name);
        $this->generalShift();

        $this->recordStage(self::STAGE_ORGANISATION);

        return $company;
    }

    /** @param array<string, mixed> $data */
    protected function firstCompany(array $data, string $name, ?string $logo): Company
    {
        $code = $this->companyCode($name);

        $company = Company::updateOrCreate(['code' => $code], [
            'name' => $name,
            'legal_name' => ($data['legal_name'] ?? '') ?: $name,
            'email' => $data['company_email'] ?? null,
            'phone' => $data['company_phone'] ?? null,
            'website' => $data['company_website'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => ($data['country'] ?? '') ?: 'India',
            'tax_id' => $data['company_tax_id'] ?? null,
            'logo_path' => $logo,
            'currency' => $data['currency'] ?? 'INR',
            'payslip_prefix' => $code,
            'watermark_enabled' => true,
            'status' => 'active',
            'is_default' => true,
        ]);

        // The migration that introduced multi-company payroll leaves a
        // placeholder entity behind, built from the settings as they stood.
        // On a fresh installation that is this company, under another name.
        $company->absorbPlaceholder();

        return $company;
    }

    /** @param array<string, mixed> $data */
    protected function headOffice(array $data, string $name): Branch
    {
        return Branch::updateOrCreate(['code' => 'HO'], [
            'name' => trim(($data['city'] ?? '') !== '' ? $data['city'].' Head Office' : $name.' Head Office'),
            'email' => $data['company_email'] ?? null,
            'phone' => $data['company_phone'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => ($data['country'] ?? '') ?: 'India',
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
            'is_head_office' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Saturday is a working day on the shift, so the branch decides which
     * Saturdays are off rather than the shift overriding it — the same rule
     * the seeded shifts follow.
     */
    protected function generalShift(): Shift
    {
        return Shift::updateOrCreate(['code' => 'GEN'], [
            'name' => 'General Shift',
            'start_time' => '09:30:00',
            'end_time' => '18:30:00',
            'grace_minutes' => 15,
            'break_minutes' => 60,
            'half_day_hours' => 4,
            'full_day_hours' => 8,
            'working_days' => [1, 2, 3, 4, 5, 6],
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    /** Initials for a short name, the first letters otherwise. */
    public function companyCode(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-]+/', trim($name)) ?: []));

        $code = count($words) > 1
            ? collect($words)->take(4)->map(fn (string $w) => mb_substr($w, 0, 1))->implode('')
            : mb_substr($words[0] ?? 'CO', 0, 4);

        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?: 'CO');
    }

    /*
    |--------------------------------------------------------------------------
    | Step three: who runs it
    |--------------------------------------------------------------------------
    */

    /**
     * The first super admin, and the end of the wizard.
     *
     * Matched on the email address so running the step twice corrects the
     * account rather than leaving a spare one able to sign in.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAdministrator(array $data): User
    {
        $admin = User::updateOrCreate(['email' => strtolower(trim((string) $data['email']))], [
            'name' => trim((string) $data['name']),
            'password' => $data['password'],
            'status' => 'active',
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        $admin->syncRoles([Roles::SUPER_ADMIN]);

        $this->administratorExists = true;
        $this->recordStage(self::STAGE_ADMINISTRATOR);

        $this->lock([
            'administrator' => $admin->email,
            'company' => Setting::get('company_name'),
        ]);

        return $admin;
    }

    /*
    |--------------------------------------------------------------------------
    | Odds and ends the first request needs
    |--------------------------------------------------------------------------
    */

    /**
     * A clone has `.env.example` and no `.env`, and no application key: the
     * first request would fail on the encrypted session cookie before it drew
     * anything. Both are made here so pointing a browser at a fresh deployment
     * lands on the setup screen instead of a stack trace.
     *
     * @return array<int, string> what had to be done, for the screen to report
     */
    public function prepareEnvironment(): array
    {
        $done = [];

        if ($this->environment->ensureExists()) {
            $done[] = 'Created .env from .env.example';
        }

        if (blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));

            $this->environment->write(['APP_KEY' => $key]);
            config(['app.key' => $key]);

            $done[] = 'Generated an application key';
        }

        return $done;
    }

    /**
     * Cached configuration would keep serving the old database credentials.
     *
     * Deleting the file is enough and does not need the console: the next
     * request rebuilds from `.env`.
     */
    public function forgetCachedConfiguration(): void
    {
        foreach ([base_path('bootstrap/cache/config.php'), base_path('bootstrap/cache/routes-v7.php')] as $cached) {
            if (is_file($cached)) {
                @unlink($cached);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Connecting
    |--------------------------------------------------------------------------
    */

    /**
     * The connection array these answers describe.
     *
     * `$database` is given explicitly only to connect to the *server* without
     * naming a schema, which is how one gets created; passing the empty string
     * is deliberate and different from leaving it out.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public function connectionConfig(array $credentials, ?string $database = null): array
    {
        $driver = (string) ($credentials['driver'] ?? 'mysql');
        $database ??= (string) ($credentials['database'] ?? '');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => (string) $credentials['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => $driver,
            'host' => (string) $credentials['host'],
            'port' => (string) $credentials['port'],
            'database' => (string) $database,
            'username' => (string) ($credentials['username'] ?? ''),
            'password' => (string) ($credentials['password'] ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];
    }

    /**
     * Open a throwaway connection, so a bad guess never disturbs the one the
     * rest of the request is using.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function probe(array $credentials, ?string $database): void
    {
        if (($credentials['driver'] ?? 'mysql') === 'sqlite') {
            $this->ensureSqliteFile((string) $credentials['database']);
        }

        config(['database.connections.installer-probe' => $this->connectionConfig($credentials, $database)]);

        DB::purge('installer-probe');

        try {
            DB::connection('installer-probe')->select('select 1');
        } finally {
            DB::purge('installer-probe');
        }
    }

    /** @param array<string, mixed> $credentials */
    protected function createDatabase(array $credentials): void
    {
        $name = (string) $credentials['database'];

        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException(
                'The database "'.$name.'" does not exist, and its name has characters in it that '
                .'stop it being created safely. Create it by hand and try again.',
            );
        }

        // Connected without naming a schema, so there is a session in which to
        // create one.
        config(['database.connections.installer-probe' => $this->connectionConfig($credentials, '')]);
        DB::purge('installer-probe');

        try {
            DB::connection('installer-probe')->statement(
                'create database if not exists `'.$name.'` character set utf8mb4 collate utf8mb4_unicode_ci',
            );
        } finally {
            DB::purge('installer-probe');
        }
    }

    protected function ensureSqliteFile(string $path): void
    {
        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0755, true);
            }

            @touch($path);
        }
    }

    /** The server answered and the login worked; there is just no schema yet. */
    protected function missingDatabase(Throwable $e): bool
    {
        return $this->driverCode($e) === 1049;
    }

    /**
     * The database's own numeric error code, whatever the driver said in words.
     *
     * The words are written by the operating system and translated into the
     * server's language — Windows says a machine "actively refused" what Linux
     * calls a connection refused, and a German install says neither. The number
     * in the brackets is the same everywhere, so decisions are made on that and
     * only the leftovers fall back to reading English.
     */
    protected function driverCode(Throwable $e): ?int
    {
        if ($e instanceof PDOException && isset($e->errorInfo[1])) {
            return (int) $e->errorInfo[1];
        }

        // PDO writes it into the message too: SQLSTATE[HY000] [2002] ...
        return preg_match('/SQLSTATE\[[^\]]+\]\s*\[(\d+)\]/', $e->getMessage(), $matches)
            ? (int) $matches[1]
            : null;
    }

    /**
     * A driver message turned into something a person can act on.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function explain(Throwable $e, array $credentials): string
    {
        $message = $e->getMessage();
        $host = ($credentials['host'] ?? 'the server').':'.($credentials['port'] ?? '');
        $code = $this->driverCode($e);

        return match (true) {
            // By number first, so this works on a Windows server and on one
            // whose database answers in a language nobody here reads.
            $code === 1045 => 'The database refused the username or password. Check both, and that the user may connect from this machine.',
            $code === 1049 => 'The database "'.($credentials['database'] ?? '').'" does not exist, and this user is not allowed to create it. Create it and try again.',
            $code === 2005 => 'The host "'.($credentials['host'] ?? '').'" could not be found.',
            $code === 2002 || $code === 2003 => 'Nothing is listening on '.$host.'. The username and password were never checked — the connection did not get that far. '
                .'Check that the database server is actually running, and that the port is right: a machine with a second MySQL on it often moves one of them to 3307.',
            $code === 2006 || $code === 2013 => 'The database at '.$host.' closed the connection before answering. It may be starting up, or refusing connections under load.',

            // Failures that never reach a driver, so they have no number.
            str_contains($message, 'could not find driver') => 'PHP has no driver for '.($credentials['driver'] ?? 'this database').'. Install the matching PDO extension and restart the web server.',
            str_contains($message, 'unable to open database') => 'The SQLite file could not be opened. Check the path and that the folder is writable.',
            str_contains($message, 'timed out') => 'The database at '.$host.' did not answer in time. A firewall between the two machines is the usual reason.',

            default => 'The database could not be reached: '.Str::limit($message, 300),
        };
    }
}
