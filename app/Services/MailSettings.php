<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Where outgoing mail is sent from, when that is decided in the interface
 * rather than in the environment file.
 *
 * Nothing here is stored until an administrator chooses something other than
 * "the environment file", so a deployment that configures mail in .env keeps
 * working exactly as it did. Once a transport is chosen it wins: somebody who
 * types an SMTP host on the settings screen expects mail to go through it.
 *
 * The password is the one value that must not be readable, so it is encrypted
 * in its row and read only here, at the moment the config is assembled.
 */
class MailSettings
{
    /** Left on this, nothing is overridden and .env decides. */
    public const FROM_ENV = 'env';

    /** What an administrator may choose, and what each one means. */
    public const TRANSPORTS = [
        self::FROM_ENV => 'Use the environment file',
        'smtp' => 'An SMTP server, set below',
        'log' => 'Write to the log, send nothing',
        'array' => 'Discard everything, send nothing',
    ];

    /**
     * How the connection is secured. Laravel calls this the scheme: smtps
     * opens TLS immediately, smtp upgrades with STARTTLS, and left empty the
     * transport decides from the port.
     */
    public const SCHEMES = [
        '' => 'Decide from the port',
        'smtp' => 'STARTTLS — usually port 587',
        'smtps' => 'SSL/TLS — usually port 465',
    ];

    public const KEYS = [
        'mail_transport', 'mail_host', 'mail_port', 'mail_username', 'mail_scheme',
    ];

    public const PASSWORD_KEY = 'mail_password';

    /** Which transport the interface has chosen, if any. */
    public function transport(): string
    {
        $value = (string) Setting::get('mail_transport', self::FROM_ENV);

        return array_key_exists($value, self::TRANSPORTS) ? $value : self::FROM_ENV;
    }

    public function overridesEnvironment(): bool
    {
        return $this->transport() !== self::FROM_ENV;
    }

    /**
     * Lay the stored transport over the config.
     *
     * Called on every request and before every queued job, because a worker
     * that started before the settings changed would otherwise keep sending
     * through the old server.
     */
    public function apply(): void
    {
        if (! $this->overridesEnvironment()) {
            return;
        }

        $transport = $this->transport();

        config(['mail.default' => $transport]);

        if ($transport !== 'smtp') {
            return;
        }

        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => Setting::get('mail_host') ?: config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => (int) (Setting::get('mail_port') ?: config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.username' => Setting::get('mail_username') ?: null,
            'mail.mailers.smtp.password' => Setting::secret(self::PASSWORD_KEY),
            // Empty means "decide from the port", which is what a null scheme
            // does; an empty string would be sent as a scheme and fail.
            'mail.mailers.smtp.scheme' => Setting::get('mail_scheme') ?: null,
        ]);
    }

    /**
     * What the screen shows, and where each value is coming from.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $overriding = $this->overridesEnvironment();

        return [
            'transport' => $this->transport(),
            'host' => Setting::get('mail_host') ?: config('mail.mailers.smtp.host'),
            'port' => Setting::get('mail_port') ?: config('mail.mailers.smtp.port'),
            'username' => Setting::get('mail_username') ?: config('mail.mailers.smtp.username'),
            'scheme' => (string) (Setting::get('mail_scheme') ?? ''),
            'has_password' => Setting::hasSecret(self::PASSWORD_KEY),
            'overriding' => $overriding,
            // What is actually in force once everything is laid over, which is
            // the only figure worth trusting on a screen about mail.
            'effective_mailer' => config('mail.default'),
            'effective_host' => config('mail.mailers.smtp.host'),
        ];
    }
}
