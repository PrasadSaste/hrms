<?php

namespace App\Support;

/**
 * Minutes, written the way a timesheet reads them.
 *
 * Reports show a lot of durations side by side, so they are padded to a fixed
 * width: "09h 17m" lines up under "13h 41m" in a column of a hundred rows.
 */
final class Duration
{
    /** Padded, for a column of figures: "09h 17m". */
    public static function hhmm(?int $minutes): string
    {
        $minutes = max(0, (int) $minutes);

        return sprintf('%02dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    /** Unpadded, for a sentence: "9h 17m", or just "17m" under an hour. */
    public static function short(?int $minutes): string
    {
        $minutes = max(0, (int) $minutes);
        $hours = intdiv($minutes, 60);

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $minutes % 60)
            : sprintf('%dm', $minutes);
    }

    /** Decimal hours, for a spreadsheet that will do arithmetic on them. */
    public static function hours(?int $minutes): float
    {
        return round(max(0, (int) $minutes) / 60, 2);
    }
}
