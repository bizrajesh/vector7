<?php

namespace Tests\Unit;

use App\Support\Format;
use App\Support\Passwords;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_indian_money_format(): void
    {
        $this->assertSame('₹12,34,567', Format::inr(1234567));
        $this->assertSame('₹999', Format::inr(999));
        $this->assertSame('₹1,00,00,000', Format::inr(10000000));
    }

    public function test_generated_password_rules(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $p = Passwords::generate();
            $this->assertSame(12, strlen($p));
            $this->assertMatchesRegularExpression('/[A-Z]/', $p);
            $this->assertMatchesRegularExpression('/[a-z]/', $p);
            $this->assertMatchesRegularExpression('/\d/', $p);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $p);
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $p);
        }
    }
}
