<?php

namespace App\Support;

/**
 * Filling {{ token }} gaps in text somebody typed.
 *
 * Substitution is a plain string replacement against a map of values. Nothing
 * is compiled, evaluated or handed to Blade, so whatever an administrator
 * writes into a notification or a letter stays text: the worst they can do to
 * a message is word it badly.
 */
final class Placeholders
{
    /** Matches {{ token }}, {{token}} and {{  token  }}. */
    public const TOKEN = '/\{\{\s*([a-z0-9_]+)\s*\}\}/i';

    /**
     * Replace every placeholder in a piece of text.
     *
     * @param  callable|null  $missing  what to put where a value is absent
     */
    public static function render(?string $template, array $data, ?callable $missing = null): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        $missing ??= fn (string $token) => '—';

        return preg_replace_callback(
            self::TOKEN,
            function (array $matches) use ($data, $missing) {
                $value = $data[$matches[1]] ?? null;

                return $value === null || $value === '' ? $missing($matches[1]) : (string) $value;
            },
            $template,
        ) ?? '';
    }

    /**
     * The tokens a piece of text actually uses.
     *
     * @return array<int, string>
     */
    public static function used(?string $template): array
    {
        preg_match_all(self::TOKEN, (string) $template, $matches);

        return collect($matches[1] ?? [])->map(fn (string $t) => strtolower($t))->unique()->values()->all();
    }
}
