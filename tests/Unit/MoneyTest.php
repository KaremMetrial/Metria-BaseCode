<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Support\Money;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    protected bool $seed = false;

    public function test_it_builds_from_decimal_using_minor_units(): void
    {
        $money = Money::fromDecimal('150.50', 'EGP');

        $this->assertSame(15050, $money->amount);
        $this->assertSame('EGP', $money->currency);
        $this->assertSame('150.50', $money->toDecimalString());
    }

    public function test_it_respects_three_decimal_currencies(): void
    {
        $money = Money::fromDecimal('1.250', 'KWD');

        $this->assertSame(1250, $money->amount);
        $this->assertSame('1.250', $money->toDecimalString());
    }

    public function test_it_adds_and_subtracts_same_currency(): void
    {
        $a = Money::of(1000, 'EGP');
        $b = Money::of(250, 'EGP');

        $this->assertSame(1250, $a->add($b)->amount);
        $this->assertSame(750, $a->subtract($b)->amount);
    }

    public function test_it_rejects_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(100, 'EGP')->add(Money::of(100, 'AED'));
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(-5, 'EGP');
    }

    public function test_it_rejects_subtraction_below_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(100, 'EGP')->subtract(Money::of(200, 'EGP'));
    }
}
