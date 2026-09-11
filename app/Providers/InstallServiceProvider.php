<?php

namespace App\Providers;

use App\Services\EnvironmentFile;
use App\Services\Installer;
use Illuminate\Support\ServiceProvider;

/**
 * What has to be true before the setup wizard can draw its first page.
 *
 * A freshly cloned deployment has no `.env`, no application key, and a
 * database that does not exist yet — while the shipped configuration keeps
 * sessions and the cache *in* that database. So the very first request would
 * fail on the encrypted session cookie before it rendered anything, which is a
 * stack trace where a person expected a welcome screen.
 *
 * While the system is not installed, therefore: the environment file and key
 * are created if they are missing, and sessions and the cache are moved onto
 * the filesystem. All of it stops the moment the lock file appears, so a
 * running installation is configured by `.env` alone and none of this is in
 * the way.
 */
class InstallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvironmentFile::class, fn () => new EnvironmentFile);
        $this->app->singleton(Installer::class, fn ($app) => new Installer($app->make(EnvironmentFile::class)));
    }

    public function boot(): void
    {
        $installer = $this->app->make(Installer::class);

        if ($installer->installed()) {
            return;
        }

        /*
         * Both drivers default to the database, which is the one thing setup
         * cannot rely on. The file system is always there, and the wizard's
         * own state is small enough to live on it.
         */
        config([
            'session.driver' => 'file',
            'cache.default' => 'array',
        ]);

        // Only over the web: a console command that has not been given a
        // database yet should say so rather than quietly write a key.
        if (! $this->app->runningInConsole()) {
            $installer->prepareEnvironment();
        }
    }
}
