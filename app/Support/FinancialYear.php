<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * The year the business reports on, which is not always the calendar one.
 *
 * India's runs April to March, and a payroll cost report that stops at
 * December is not the number anybody is asked for. The starting month lives on
 * the settings screen; leave it at January and everything below reduces to the
 * calendar year with no special cases.
 *
 * A financial year is named for the year it *starts* in: `2026` is April 2026
 * to March 2027, written "FY 2026–27".
 */
final class FinancialYear
{
    /** The month a financial year opens in, 1 to 12. */
    public static function startMonth(): int
    {
        $month = (int) Setting::get('financial_year_start_month', 4);

        return $month >= 1 && $month <= 12 ? $month : 1;
    }

    /** Whether the financial year is simply the calendar year. */
    public static function isCalendar(): bool
    {
        return self::startMonth() === 1;
    }

    /** The financial year a date falls in, named for the year it started. */
    public static function of(?Carbon $date = null): int
    {
        $date = $date ? $date->copy() : Carbon::today();

        return (int) $date->month >= self::startMonth()
            ? (int) $date->year
            : (int) $date->year - 1;
    }

    public static function current(): int
    {
        return self::of();
    }

    public static function start(int $year): Carbon
    {
        return Carbon::create($year, self::startMonth(), 1)->startOfDay();
    }

    public static function end(int $year): Carbon
    {
        return self::start($year)->addYear()->subDay()->endOfDay();
    }

    /**
     * The twelve months of a financial year, in the order they happen.
     *
     * @return array<int, Carbon> the first of each month
     */
    public static function months(int $year): array
    {
        $cursor = self::start($year);

        return collect(range(0, 11))
            ->map(fn (int $offset) => $cursor->copy()->addMonths($offset))
            ->all();
    }

    /** "FY 2026–27", or just "2026" where the year is the calendar one. */
    public static function label(int $year): string
    {
        if (self::isCalendar()) {
            return (string) $year;
        }

        return sprintf('FY %d–%02d', $year, ($year + 1) % 100);
    }

    /**
     * Recent financial years, newest first, for a year picker.
     *
     * @return array<int, string> year => label
     */
    public static function options(int $count = 6): array
    {
        $current = self::current();

        return collect(range($current, $current - ($count - 1)))
            ->mapWithKeys(fn (int $year) => [$year => self::label($year)])
            ->all();
    }
}
