<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_input_is_parsed_without_floating_point_rounding(): void
    {
        $this->assertSame(29, Money::parse('0.29'));
        $this->assertSame(1999, Money::parse('19,99'));
        $this->assertSame(100000001, Money::parse('1000000.01'));
        $this->assertSame('1000000.01', Money::toDecimalString(100000001));
        $this->assertNull(Money::parse('1e3'));
        $this->assertNull(Money::parse('-1.00'));
        $this->assertNull(Money::parse('0.001'));
        $this->assertNull(Money::parse('1,000.00'));
    }
}
