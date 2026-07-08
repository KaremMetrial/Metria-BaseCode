<?php

declare(strict_types=1);

namespace App\Domain\Currency\Services;

use App\Core\Contracts\CurrencyRegistryResolver;
use App\Core\Support\Money;
use NumberFormatter;

class CurrencyFormatter
{
    public function __construct(
        protected CurrencyRegistryResolver $resolver
    ) {}

    /**
     * Format a Money object according to a given locale.
     * Canonical formatting utilizes PHP 'intl' NumberFormatter class.
     * Falls back to static configuration rules if the extension is unavailable.
     *
     * @param  Money  $money  The money value object.
     * @param  string  $locale  The target locale (e.g. 'en_US', 'ar_EG').
     * @return string The formatted currency representation.
     */
    public function format(Money $money, string $locale = 'en'): string
    {
        $currencyCode = strtoupper($money->currency);
        $decimalValue = $money->toDecimal();

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $minorUnits = $this->resolver->minorUnitsFor($currencyCode);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $minorUnits);

            $formatted = $formatter->formatCurrency($decimalValue, $currencyCode);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        // Fallback layout using static configuration if intl is unavailable
        $fallbackConfig = config("currencies.formatting.{$currencyCode}", [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_placement' => 'before',
        ]);

        $minorUnits = $this->resolver->minorUnitsFor($currencyCode);
        $symbol = $currencyCode; // Fallback symbol is ISO code itself

        $numberPart = number_format(
            $decimalValue,
            $minorUnits,
            $fallbackConfig['decimal_separator'] ?? '.',
            $fallbackConfig['thousands_separator'] ?? ','
        );

        $placement = $fallbackConfig['symbol_placement'] ?? 'before';

        return $placement === 'before'
            ? "{$symbol} {$numberPart}"
            : "{$numberPart} {$symbol}";
    }
}
