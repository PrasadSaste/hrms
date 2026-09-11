<?php

namespace Tests\Unit;

use App\Services\PayrollService;
use PHPUnit\Framework\TestCase;

/**
 * Pure calculation checks that need no database.
 */
class PayrollCalculationTest extends TestCase
{
    public function test_currency_symbols_are_resolved(): void
    {
        $this->assertSame("\u{20B9}", PayrollService::currencySymbol('INR'));
        $this->assertSame('$', PayrollService::currencySymbol('USD'));
        $this->assertSame("\u{20AC}", PayrollService::currencySymbol('EUR'));
        $this->assertSame("\u{A3}", PayrollService::currencySymbol('GBP'));
    }

    public function test_an_unknown_currency_falls_back_to_its_code(): void
    {
        $this->assertSame('JPY ', PayrollService::currencySymbol('JPY'));
    }
}
