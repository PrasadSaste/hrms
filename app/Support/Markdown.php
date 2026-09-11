<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Markdown that people type, rendered safely.
 *
 * Raw HTML is escaped rather than passed through, so a stray tag in a help
 * guide or a notification template shows up as text instead of running in the
 * browser of whoever reads it.
 */
final class Markdown
{
    /**
     * @param  bool  $hardBreaks  keep every line break the author typed
     *
     * In a guide or an email, a paragraph wrapped over several lines should
     * read as one paragraph. In a letter, a line somebody put on its own line —
     * "Employee code: ..." above "Place of work: ..." — has to stay there.
     */
    public static function html(?string $markdown, bool $hardBreaks = false): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, array_merge([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ], $hardBreaks ? ['renderer' => ['soft_break' => "<br />\n"]] : []));
    }

    /** The first plain-text words of a document, for search results. */
    public static function excerpt(?string $markdown, int $length = 160): string
    {
        $text = trim(html_entity_decode(strip_tags(self::html($markdown))));

        return Str::limit(preg_replace('/\s+/', ' ', $text) ?? '', $length);
    }
}
