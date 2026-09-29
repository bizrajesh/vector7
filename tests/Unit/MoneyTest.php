<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_indian_grouping(): void
    {
        $this->assertSame('₹12,34,567', Money::inr(1234567));
        $this->assertSame('₹12,34,567.50', Money::inr(1234567.5, true));
        $this->assertSame('₹999', Money::inr(999));
        $this->assertSame('₹1.84 Cr', Money::short(18400000));
        $this->assertSame('₹42.6 L', Money::short(4260000));
    }
}
