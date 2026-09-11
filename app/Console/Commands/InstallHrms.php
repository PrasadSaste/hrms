<?php

namespace App\Console\Commands;

use App\Services\Installer;
use App\Support\Roles;
use App\Support\SystemRequirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The setup wizard, for a deployment that has no browser in it.
 *
 * Asks exactly what the web wizard asks and hands the answers to the same
 * {@see Installer}, so a system provisioned from a script and one set up by
 * hand end up identical. Every answer can also arrive as an option, which is
 * what makes it usable from a deployment script:
 *
 *   php artisan hrms:install --company="Acme" --admin-email=… --admin-password=… --no-interaction
 */
class InstallHrms extends Command
{
    protected $signature = 'hrms:install
        {--company= : The organisation this system belongs to.}
        {--admin-name= : The first administrator, by name.}
        {--admin-email= : The first administrator, by email address.}
        {--admin-password= : Their password. Prompted for when it is left out.}
        {--currency=INR : Currency code for salaries.}
        {--timezone=Asia/Kolkata : The time zone attendance is judged in.}
        {--skip-checks : Install even though this server fails a requirement.}
        {--force : Set up again over an installation that is already finished.}';

    protected $description = 'Set the HRMS up on this server: schema, reference data and the first administrator';

    public function handle(Installer $installer, SystemRequirements $requirements): int
    {
        $this->line('');
        $this->info('  Setting up the HRMS');
        $this->line('');

        if ($installer->installed() && ! $this->option('force')) {
            $this->warn('  This system is already set up.');

            if ($details = $installer->details()) {
                $this->line('  Installed '.($details['installed_at'] ?? 'at some point').'.');
            }

            $this->line('  Run it again with --force to set up over the top of it.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            if (config('install.locked')) {
                $this->error('  INSTALL_LOCKED is true in .env. Set it to false before installing over an existing system.');

                return self::FAILURE;
            }

            // Take the lock off, or every later step would still be told this
            // system is finished.
            $installer->unlock();
        }

        if (! $this->checkServer($requirements)) {
            return self::FAILURE;
        }

        foreach ($installer->prepareEnvironment() as $done) {
            $this->line('  <fg=green>✓</> '.$done);
        }

        // The database is whatever `.env` already points at. A script that
        // wants a different one writes it there before calling this, which is
        // how every other Laravel deployment does it.
        if (! $this->migrate($installer)) {
            return self::FAILURE;
        }

        $company = $this->organisation($installer);
        $admin = $this->administrator($installer);

        if ($admin === null) {
            return self::FAILURE;
        }

        $this->line('');
        $this->info('  Done. '.$company.' is ready.');
        $this->line('');
        $this->table([], [
            ['Sign in at', config('app.url').'/login'],
            ['As', $admin],
            ['Role', Roles::label(Roles::SUPER_ADMIN)],
        ]);
        $this->line('');
        $this->comment('  Next: point mail at your own server, check the professional tax slabs for');
        $this->comment('  your state, and add the scheduler to cron. All three are in DEPLOYMENT.md.');
        $this->line('');

        return self::SUCCESS;
    }

    protected function checkServer(SystemRequirements $requirements): bool
    {
        $failures = $requirements->failures();

        if ($failures === []) {
            $this->line('  <fg=green>✓</> This server has everything it needs.');

            return true;
        }

        $this->line('');
        $this->error('  This server is missing something:');

        foreach ($failures as $failure) {
            $this->line('    <fg=red>✕</> '.$failure['name'].' — '.$failure['found'].' ('.$failure['detail'].')');
        }

        $this->line('');

        if ($this->option('skip-checks')) {
            $this->warn('  Carrying on anyway, because --skip-checks was given.');

            return true;
        }

        $this->line('  Fix these and try again, or pass --skip-checks to install regardless.');

        return false;
    }

    protected function migrate(Installer $installer): bool
    {
        $this->line('');
        $this->line('  Creating the tables and loading the reference data...');

        try {
            $installer->migrate();
        } catch (Throwable $e) {
            $this->line('');
            $this->error('  The database could not be set up.');
            $this->line('  '.$e->getMessage());
            $this->line('');
            $this->line('  Check the DB_ settings in .env, then run this again.');

            return false;
        }

        $this->line('  <fg=green>✓</> Roles, permissions, leave types, salary components and help guides are in place.');

        return true;
    }

    protected function organisation(Installer $installer): string
    {
        $name = $this->option('company') ?: (
            $this->input->isInteractive()
                ? text('What is the organisation called?', required: true)
                : 'My Company'
        );

        $installer->saveOrganisation([
            'company_name' => $name,
            'legal_name' => $name,
            'currency' => strtoupper((string) $this->option('currency')),
            'timezone' => (string) $this->option('timezone'),
            'country' => 'India',
        ]);

        $this->line('  <fg=green>✓</> '.$name.', its head office and a general shift are set up.');

        return $name;
    }

    /** The administrator's email address, or null when the answers were no good. */
    protected function administrator(Installer $installer): ?string
    {
        $data = [
            'name' => $this->option('admin-name') ?: (
                $this->input->isInteractive() ? text('Who is the administrator?', required: true) : 'Administrator'
            ),
            'email' => $this->option('admin-email') ?: (
                $this->input->isInteractive() ? text('Their email address', required: true) : null
            ),
            'password' => $this->option('admin-password') ?: (
                $this->input->isInteractive() ? password('A password for them', required: true) : null
            ),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        if ($validator->fails()) {
            $this->line('');
            $this->error('  The administrator could not be created:');

            foreach ($validator->errors()->all() as $message) {
                $this->line('    <fg=red>✕</> '.$message);
            }

            $this->line('');
            $this->line('  The schema and the reference data are in place, so run this again with');
            $this->line('  --admin-name, --admin-email and --admin-password to finish.');

            return null;
        }

        if ($this->input->isInteractive()
            && ! $this->option('admin-password')
            && ! confirm('Create '.$data['email'].' as the administrator?', default: true)) {
            $this->warn('  Stopped. Nothing else was written.');

            return null;
        }

        $admin = $installer->createAdministrator($data);

        $this->line('  <fg=green>✓</> '.$admin->email.' can sign in and administer everything.');

        return $admin->email;
    }
}
