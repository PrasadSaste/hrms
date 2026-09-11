<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * How an amount is written down. No database: the currency is always named,
 * because the default only exists once settings are loaded.
 */
class MoneyTest extends TestCase
{
    public function test_rupees_group_two_at_a_time_above_the_hundreds(): void
    {
        $this->assertSame('999.00', Money::format(999, 2, 'INR'));
        $this->assertSame('1,000.00', Money::format(1000, 2, 'INR'));
        $this->assertSame('90,000.00', Money::format(90000, 2, 'INR'));
        $this->assertSame('9,90,000.00', Money::format(990000, 2, 'INR'));
        $this->assertSame('99,00,000.00', Money::format(9900000, 2, 'INR'));
        $this->assertSame('9,90,00,000.00', Money::format(99000000, 2, 'INR'));
        $this->assertSame('1,00,00,00,000.00', Money::format(1000000000, 2, 'INR'));
    }

    public function test_other_currencies_keep_the_grouping_their_readers_expect(): void
    {
        $this->assertSame('990,000.00', Money::format(990000, 2, 'USD'));
        $this->assertSame('990,000.00', Money::format(990000, 2, 'GBP'));
    }

    public function test_a_negative_amount_keeps_its_sign_outside_the_digits(): void
    {
        $this->assertSame('-9,90,000.50', Money::format(-990000.5, 2, 'INR'));
    }

    public function test_the_symbol_leads_the_amount(): void
    {
        $this->assertSame("\u{20B9}9,90,000.00", Money::withSymbol(990000, 'INR'));
        $this->assertSame('$990,000.00', Money::withSymbol(990000, 'USD'));
        $this->assertSame("\u{20B9}9,90,000", Money::withSymbol(990000, 'INR', 0));
    }

    public function test_nothing_reads_as_zero_rather_than_blank(): void
    {
        $this->assertSame("\u{20B9}0.00", Money::withSymbol(null, 'INR'));
    }

    public function test_rupees_are_spelled_out_in_lakh_and_crore(): void
    {
        $this->assertSame('Rupees Nine Lakh Ninety Thousand Only', Money::inWords(990000, 'INR'));
        $this->assertSame('Rupees One Crore Only', Money::inWords(10000000, 'INR'));
        $this->assertSame('Rupees Twenty Five Crore Only', Money::inWords(250000000, 'INR'));
        $this->assertSame('Rupees One Hundred Five Only', Money::inWords(105, 'INR'));
    }

    public function test_paise_are_named_after_the_rupees(): void
    {
        $this->assertSame(
            'Rupees Seventy Three Thousand One Hundred Fifty and Fifty Paise Only',
            Money::inWords(73150.50, 'INR'),
        );
    }

    public function test_other_currencies_are_spelled_the_western_way(): void
    {
        $this->assertSame(
            'Dollars Nine Hundred Ninety Thousand and Twenty Five Cents Only',
            Money::inWords(990000.25, 'USD'),
        );
    }
}
