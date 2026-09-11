<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\PayrollService;

/**
 * How an amount is written down.
 *
 * India groups digits two at a time above the hundreds — ₹9,90,000.00, not
 * ₹990,000.00 — and counts in lakh and crore rather than million. Every amount
 * the system prints goes through here so a payslip and a letter about the same
 * salary can never disagree with each other, or with what somebody's bank
 * statement will say.
 *
 * Other currencies keep the grouping their readers expect, so a company paying
 * in dollars still sees $990,000.00.
 */
class Money
{
    /** Currencies written with the Indian two-two-three grouping. */
    protected const INDIAN = ['INR'];

    /** Words for each step of the Indian scale, largest first. */
    protected const SCALE = [
        10000000 => 'Crore',
        100000 => 'Lakh',
        1000 => 'Thousand',
        100 => 'Hundred',
    ];

    /** The digits alone, grouped for the currency: 9,90,000.00. */
    public static function format(float|int|string|null $amount, int $decimals = 2, ?string $currency = null): string
    {
        $amount = (float) ($amount ?? 0);

        if (! self::isIndian($currency)) {
            return number_format($amount, $decimals);
        }

        $sign = $amount < 0 ? '-' : '';
        $plain = number_format(abs($amount), $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $plain, 2), 2, '');

        return $sign.self::group($whole).($fraction === '' ? '' : '.'.$fraction);
    }

    /** The amount with its currency symbol in front: ₹9,90,000.00. */
    public static function withSymbol(float|int|string|null $amount, ?string $currency = null, int $decimals = 2): string
    {
        return PayrollService::currencySymbol($currency).self::format($amount, $decimals, $currency);
    }

    /**
     * The amount spelled out for the foot of a payslip.
     *
     * In rupees this counts the Indian way — "Nine Lakh Ninety Thousand" —
     * because that is what a cheque, a bank and an auditor expect to read.
     */
    public static function inWords(float $amount, ?string $currency = null): string
    {
        $currency = self::currency($currency);
        [$major, $minor] = self::names($currency);

        $whole = (int) floor(abs($amount));
        $fraction = (int) round((abs($amount) - $whole) * 100);

        $words = self::isIndian($currency) ? self::indianWords($whole) : self::spell($whole);
        $result = $major.' '.($amount < 0 ? 'Minus ' : '').$words;

        if ($fraction > 0) {
            $result .= ' and '.(self::isIndian($currency) ? self::indianWords($fraction) : self::spell($fraction)).' '.$minor;
        }

        return trim($result).' Only';
    }

    /** Whether this currency groups its digits the Indian way. */
    public static function isIndian(?string $currency = null): bool
    {
        return in_array(self::currency($currency), self::INDIAN, true);
    }

    /** Last three digits, then pairs: 9,90,00,000. */
    protected static function group(string $whole): string
    {
        if (strlen($whole) <= 3) {
            return $whole;
        }

        $last = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $pairs = [];

        while (strlen($rest) > 2) {
            $pairs[] = substr($rest, -2);
            $rest = substr($rest, 0, -2);
        }

        if ($rest !== '') {
            $pairs[] = $rest;
        }

        return implode(',', array_reverse($pairs)).','.$last;
    }

    /** "Nine Lakh Ninety Thousand", built from the scale downwards. */
    protected static function indianWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        foreach (self::SCALE as $value => $name) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $number -= $count * $value;
                // A crore is itself counted the Indian way, so a hundred crore
                // reads "One Hundred Crore" rather than running out of names.
                $parts[] = self::indianWords($count).' '.$name;
            }
        }

        if ($number > 0) {
            $parts[] = self::spell($number);
        }

        return implode(' ', $parts);
    }

    /** Numbers below a hundred, in words. */
    protected static function spell(int $number): string
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);

        return ucwords(str_replace('-', ' ', $formatter->format($number)));
    }

    /** @return array{0: string, 1: string} */
    protected static function names(string $currency): array
    {
        return match ($currency) {
            'INR' => ['Rupees', 'Paise'],
            'USD' => ['Dollars', 'Cents'],
            'EUR' => ['Euros', 'Cents'],
            'GBP' => ['Pounds', 'Pence'],
            'AED' => ['Dirhams', 'Fils'],
            default => [$currency, 'Cents'],
        };
    }

    protected static function currency(?string $currency = null): string
    {
        return strtoupper($currency ?: (string) Setting::get('currency', 'INR'));
    }
}
