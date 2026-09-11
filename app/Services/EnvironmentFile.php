<?php

namespace App\Services;

use RuntimeException;

/**
 * Edits `.env` in place, one key at a time.
 *
 * The setup wizard has to write the database credentials before it can run a
 * single migration, and the file it writes into is the one a human will read
 * afterwards: comments, blank lines and the order of the keys all survive, so
 * the result still looks like the file that shipped rather than a dump. A key
 * already present is replaced where it stands; a key that is new is appended.
 *
 * The write goes to a temporary file first and is renamed over the original,
 * because a half-written `.env` takes the whole site down.
 */
class EnvironmentFile
{
    public function __construct(protected ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? base_path('.env');
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * Create `.env` from `.env.example` if it is missing.
     *
     * Returns true when a file was created, so the caller can say so.
     */
    public function ensureExists(): bool
    {
        if ($this->exists()) {
            return false;
        }

        $example = base_path('.env.example');

        if (! is_file($example)) {
            throw new RuntimeException('There is no .env.example to copy, so .env cannot be created.');
        }

        $this->put(file_get_contents($example));

        return true;
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        if (! preg_match($this->pattern($key), $this->contents(), $matches)) {
            return null;
        }

        return $this->unquote(trim($matches[1]));
    }

    /**
     * Write one or more keys.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function write(array $values): void
    {
        $this->ensureExists();

        $contents = $this->contents();

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);

            $contents = preg_match($this->pattern($key), $contents)
                ? preg_replace($this->pattern($key), $this->escapeReplacement($line), $contents, 1)
                : rtrim($contents, "\n")."\n".$line."\n";
        }

        $this->put($contents);
    }

    protected function contents(): string
    {
        return (string) file_get_contents($this->path());
    }

    /**
     * Replace the file as a whole, via a temporary file in the same directory
     * so the rename is atomic and a crash cannot leave a truncated `.env`.
     */
    protected function put(string $contents): void
    {
        $path = $this->path();
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write to '.dirname($path).'. Check that the folder is writable.');
        }

        // Readable by the web server and nobody else: this file holds the
        // database password and the application key.
        @chmod($temporary, 0640);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Could not replace '.$path.'. Check that the file is writable.');
        }
    }

    /**
     * Matches the whole line the key sits on, so it is replaced entire.
     *
     * Spaces and tabs rather than `\s`, which would also match the newline
     * before the key and swallow the blank line above it on every write.
     */
    protected function pattern(string $key): string
    {
        return '/^[ \t]*'.preg_quote($key, '/').'[ \t]*=(.*)$/m';
    }

    /**
     * Quote anything that is not a bare word.
     *
     * A password with a space or a `#` in it is otherwise read as a comment or
     * cut short, which shows up much later as a connection that will not open.
     */
    protected function quote(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.\-\/:]+$/', $value)) {
            return $value;
        }

        // Single quotes first, because they are literal: a password full of
        // $ and \ signs needs no escaping and cannot be read as a reference to
        // another variable. Only a value containing a single quote of its own
        // has to go in double quotes, where those three do need escaping.
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    protected function unquote(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return str_replace(['\\"', '\\\\'], ['"', '\\'], substr($value, 1, -1));
        }

        if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /** `$` and `\` mean something to preg_replace's replacement, and not here. */
    protected function escapeReplacement(string $value): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }
}
