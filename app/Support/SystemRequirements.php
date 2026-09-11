<?php

namespace App\Support;

/**
 * What a server needs before the HRMS will run on it.
 *
 * A catalogue, like the permissions and the automations: the setup screen and
 * `hrms:install` both read this list rather than each keeping their own, so a
 * dependency added here is checked by both without either being touched.
 *
 * Nothing here reaches the database — this is the one check that has to work
 * on a machine where nothing is configured yet.
 */
class SystemRequirements
{
    /**
     * Extensions the application will not start without.
     *
     * Taken from what the framework and the PDF library actually declare they
     * need, not from a wishlist: a check that fails on a server the software
     * would have run on perfectly well is worse than no check at all.
     *
     * @var array<string, string>
     */
    public const EXTENSIONS = [
        'pdo' => 'Talking to the database at all',
        'pdo_mysql' => 'MySQL and MariaDB',
        'mbstring' => 'Names and rupee symbols in anything but plain ASCII',
        'openssl' => 'Encrypted settings, sessions and the SMTP password',
        'tokenizer' => 'Compiling the page templates',
        'dom' => 'Drawing salary slips and letters as PDFs',
        'libxml' => 'Reading and writing those documents',
        'iconv' => 'Converting text between encodings',
        'ctype' => 'Framework internals',
        'json' => 'Framework internals',
        'filter' => 'Validating what is typed into a form',
        'session' => 'Keeping people signed in',
        'fileinfo' => 'Checking what an uploaded logo or document actually is',
        'intl' => 'Writing an amount out in words, which every payslip does',
    ];

    /**
     * Extensions worth having, that nothing refuses to start without.
     *
     * Reported so a deployment can be improved, never so it can be blocked.
     *
     * @var array<string, string>
     */
    public const RECOMMENDED = [
        'gd' => 'A photographic logo on a letterhead. Without it, only vector logos draw.',
        'curl' => 'Faster and more reliable calls out to other services.',
    ];

    /**
     * Directories the web server's user must be able to write to.
     *
     * The base path is on the list because the installer writes `.env` into it.
     *
     * @var array<string, string>
     */
    public const WRITABLE = [
        '' => 'The environment file',
        'storage/app' => 'Uploaded logos, documents and salary slips',
        'storage/framework' => 'Sessions, cached views and queued work',
        'storage/logs' => 'The application log',
        'bootstrap/cache' => 'The compiled configuration',
    ];

    /**
     * Every check, each answered.
     *
     * @return array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}>
     */
    public function all(): array
    {
        return array_merge([$this->php()], $this->extensions(), $this->recommended(), $this->writable());
    }

    /**
     * Only the checks that decide whether setup may go ahead.
     *
     * @return array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}>
     */
    public function required(): array
    {
        return array_values(array_filter($this->all(), fn (array $check) => $check['required']));
    }

    /**
     * What is missing and actually matters.
     *
     * @return array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->required(), fn (array $check) => ! $check['met']));
    }

    public function satisfied(): bool
    {
        return $this->failures() === [];
    }

    /** @return array{group: string, name: string, detail: string, met: bool, found: string, required: bool} */
    public function php(): array
    {
        $minimum = (string) config('install.php', '8.3.0');

        return [
            'group' => 'PHP',
            'name' => 'PHP '.$minimum.' or newer',
            'detail' => 'The language the application is written in',
            'met' => version_compare(PHP_VERSION, $minimum, '>='),
            'found' => PHP_VERSION,
            'required' => true,
        ];
    }

    /** @return array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}> */
    public function extensions(): array
    {
        $checks = [];

        foreach (self::EXTENSIONS as $extension => $detail) {
            $loaded = extension_loaded($extension);

            $checks[] = [
                'group' => 'PHP extensions',
                'name' => $extension,
                'detail' => $detail,
                'met' => $loaded,
                'found' => $loaded ? 'Loaded' : 'Missing',
                'required' => true,
            ];
        }

        return $checks;
    }

    /** @return array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}> */
    public function recommended(): array
    {
        $checks = [];

        foreach (self::RECOMMENDED as $extension => $detail) {
            $loaded = extension_loaded($extension);

            $checks[] = [
                'group' => 'Worth having',
                'name' => $extension,
                'detail' => $detail,
                'met' => $loaded,
                'found' => $loaded ? 'Loaded' : 'Not installed',
                'required' => false,
            ];
        }

        return $checks;
    }

    /** @return array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}> */
    public function writable(): array
    {
        $checks = [];

        foreach (self::WRITABLE as $relative => $detail) {
            $path = rtrim(base_path($relative), '/');
            $writable = is_dir($path) && is_writable($path);

            $checks[] = [
                'group' => 'Folder permissions',
                'name' => $relative === '' ? '/' : $relative.'/',
                'detail' => $detail,
                'met' => $writable,
                'found' => $writable ? 'Writable' : (is_dir($path) ? 'Read only' : 'Missing'),
                'required' => true,
            ];
        }

        return $checks;
    }

    /**
     * The checks arranged for a screen that lists them under headings.
     *
     * @return array<string, array<int, array{group: string, name: string, detail: string, met: bool, found: string, required: bool}>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $check) {
            $grouped[$check['group']][] = $check;
        }

        return $grouped;
    }
}
