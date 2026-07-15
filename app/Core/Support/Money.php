<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Contracts\CurrencyRegistryResolver;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Money value object stored in minor units (piasters, cents, fils).
 * Never use floats for money — this class exists so you don't have to.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public int $amount,      // minor units
        public string $currency, // ISO-4217, e.g. EGP, AED, USD
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException(__('currency.money_negative'));
        }
    }

    public static function of(int $minorUnits, ?string $currency = null): self
    {
        $cfgCurrency = config('payments.currency', 'EGP');
        $currencyStr = is_string($cfgCurrency) ? $cfgCurrency : 'EGP';
        return new self($minorUnits, strtoupper($currency ?? $currencyStr));
    }

    /**
     * Build from a decimal (e.g. "150.50" EGP => 15050 piasters).
     */
    public static function fromDecimal(float|string $decimal, ?string $currency = null): self
    {
        $cfgCurrency = config('payments.currency', 'EGP');
        $currencyStr = is_string($cfgCurrency) ? $cfgCurrency : 'EGP';
        $currency = strtoupper($currency ?? $currencyStr);
        $units = self::minorUnitsFor($currency);

        $minor = (int) round(((float) $decimal) * (10 ** $units));

        return new self($minor, $currency);
    }

    public static function minorUnitsFor(string $currency): int
    {
        $currency = strtoupper($currency);

        try {
            if (function_exists('app') && app()->bound(CurrencyRegistryResolver::class)) {
                return app(CurrencyRegistryResolver::class)->minorUnitsFor($currency);
            }
        } catch (\Throwable $e) {
            // Fallback during early bootstrap or tests without container
        }

        $minorUnits = config('payments.minor_units.'.$currency);
        return is_numeric($minorUnits) ? (int) $minorUnits : 2;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException(__('currency.money_negative_result'));
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function toDecimal(): float
    {
        return $this->amount / (10 ** self::minorUnitsFor($this->currency));
    }

    public function toDecimalString(): string
    {
        return number_format($this->toDecimal(), self::minorUnitsFor($this->currency), '.', '');
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(__('currency.currency_mismatch', ['current' => $this->currency, 'other' => $other->currency]));
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->toDecimalString().' '.$this->currency,
        ];
    }

    public function __toString(): string
    {
        return $this->toDecimalString().' '.$this->currency;
    }
}
