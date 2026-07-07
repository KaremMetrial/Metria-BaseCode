<?php

declare(strict_types=1);

namespace App\Domain\Currency\Services;

use App\Core\Support\Money;

class CurrencyConversionService
{
    public const ROUND_DOWN = 5;

    public function __construct(
        protected ExchangeRateService $rateService,
        protected CurrencyRegistryResolverImpl $registryResolver
    ) {}

    /**
     * Convert an amount from one currency to another using high-precision BCMath arithmetic.
     *
     * @param  Money  $money  The money object to convert.
     * @param  string  $targetCurrency  The target ISO currency code.
     * @param  int|null  $roundingMode  Rounding mode constant (e.g. PHP_ROUND_HALF_EVEN).
     * @return array Contains converted Money object and rate metadata snapshot.
     */
    public function convert(Money $money, string $targetCurrency, ?int $roundingMode = null): array
    {
        $sourceCurrency = strtoupper($money->currency);
        $targetCurrency = strtoupper($targetCurrency);
        $roundingMode ??= $this->resolveRoundingMode(config('currencies.default_rounding_mode'));

        if ($sourceCurrency === $targetCurrency) {
            return [
                'money' => $money,
                'snapshot' => [
                    'source_currency' => $sourceCurrency,
                    'target_currency' => $targetCurrency,
                    'exchange_rate' => '1.00000000000000',
                    'rate_provider' => 'system',
                    'rate_provider_version' => '1.0',
                    'conversion_direction' => 'multiply',
                    'rounding_mode_used' => $this->roundingModeToString($roundingMode),
                    'conversion_algorithm_version' => config('currencies.default_algorithm_version', 'v1'),
                    'rate_captured_at' => now()->toDateTimeString(),
                    'converted_amount' => $money->amount,
                    'converted_amount_decimal' => $money->toDecimalString(),
                ],
            ];
        }

        // Find default/base currency
        $baseCurrencyCode = config('currencies.base_currency', config('payments.currency', 'EGP'));

        // Fetch rates to base
        $sourceRate = null;
        $targetRate = null;

        if ($sourceCurrency !== $baseCurrencyCode) {
            $sourceRate = $this->rateService->getActiveRate($sourceCurrency, now());
        }

        if ($targetCurrency !== $baseCurrencyCode) {
            $targetRate = $this->rateService->getActiveRate($targetCurrency, now());
        }

        $sourceRateVal = $sourceRate ? $sourceRate->rate_to_base : '1.00000000000000';
        $targetRateVal = $targetRate ? $targetRate->rate_to_base : '1.00000000000000';

        // Calculate direct or cross-rate to convert from source to target
        // Target = Source * (SourceRate / TargetRate)
        $exchangeRate = bcdiv((string) $sourceRateVal, (string) $targetRateVal, 18);

        // Convert the source amount in minor units to its decimal form
        $sourceDecimal = $money->toDecimalString();

        // Perform the multiplication to target decimal
        $targetDecimal = bcmul($sourceDecimal, $exchangeRate, 18);

        // Round to target currency's minor units
        $targetMinorUnits = $this->registryResolver->minorUnitsFor($targetCurrency);
        $roundedDecimal = self::bcRound($targetDecimal, $targetMinorUnits, $roundingMode);

        // Build target Money object
        $factor = bcpow('10', (string) $targetMinorUnits, 0);
        $targetMinorAmount = (int) bcmul($roundedDecimal, $factor, 0);

        $targetMoney = new Money($targetMinorAmount, $targetCurrency);

        // Identify primary provider used
        $providerName = $targetRate ? $targetRate->provider_name : ($sourceRate ? $sourceRate->provider_name : 'system');
        $providerVersion = $targetRate ? $targetRate->provider_version : ($sourceRate ? $sourceRate->provider_version : '1.0');

        return [
            'money' => $targetMoney,
            'snapshot' => [
                'source_currency' => $sourceCurrency,
                'target_currency' => $targetCurrency,
                'exchange_rate' => self::bcRound($exchangeRate, config('currencies.scale', 14), PHP_ROUND_HALF_UP),
                'rate_provider' => $providerName,
                'rate_provider_version' => $providerVersion,
                'conversion_direction' => ($targetCurrency === $baseCurrencyCode) ? 'multiply' : (($sourceCurrency === $baseCurrencyCode) ? 'divide' : 'cross'),
                'rounding_mode_used' => $this->roundingModeToString($roundingMode),
                'conversion_algorithm_version' => config('currencies.default_algorithm_version', 'v1'),
                'rate_captured_at' => now()->toDateTimeString(),
                'converted_amount' => $targetMinorAmount,
                'converted_amount_decimal' => $targetMoney->toDecimalString(),
            ],
        ];
    }

    /**
     * High-precision round implementation for BCMath.
     */
    public static function bcRound(string $number, int $precision, int $mode = PHP_ROUND_HALF_UP): string
    {
        if (strpos($number, '.') === false) {
            return bcadd($number, '0', $precision);
        }

        [$whole, $fraction] = explode('.', $number);

        if (strlen($fraction) <= $precision) {
            return bcadd($number, '0', $precision);
        }

        $kept = substr($fraction, 0, $precision);
        $nextDigit = (int) $fraction[$precision];
        $rest = substr($fraction, $precision + 1);

        $sign = strpos($whole, '-') === 0 ? '-' : '';
        $unsignedWhole = ltrim($whole, '-');

        $shouldIncrement = false;

        if ($mode === PHP_ROUND_HALF_UP) {
            $shouldIncrement = $nextDigit >= 5;
        } elseif ($mode === PHP_ROUND_HALF_EVEN) {
            if ($nextDigit > 5) {
                $shouldIncrement = true;
            } elseif ($nextDigit === 5) {
                $hasNonZeroRest = $rest !== '' && ltrim($rest, '0') !== '';
                if ($hasNonZeroRest) {
                    $shouldIncrement = true;
                } else {
                    $lastKeptDigit = $precision > 0 ? (int) $kept[$precision - 1] : (int) substr($unsignedWhole, -1);
                    $shouldIncrement = ($lastKeptDigit % 2 !== 0);
                }
            }
        } elseif ($mode === self::ROUND_DOWN) {
            $shouldIncrement = false;
        }

        $base = $precision > 0 ? $unsignedWhole.'.'.$kept : $unsignedWhole;

        if ($shouldIncrement) {
            $increment = bcpow('10', (string) -$precision, $precision + 2);
            $base = bcadd($base, $increment, $precision);
        } else {
            $base = bcadd($base, '0', $precision);
        }

        return $sign.$base;
    }

    protected function resolveRoundingMode(string $mode): int
    {
        return match (strtolower($mode)) {
            'half_up' => PHP_ROUND_HALF_UP,
            'down' => self::ROUND_DOWN,
            default => PHP_ROUND_HALF_EVEN,
        };
    }

    protected function roundingModeToString(int $mode): string
    {
        return match ($mode) {
            PHP_ROUND_HALF_UP => 'half_up',
            self::ROUND_DOWN => 'down',
            default => 'half_even',
        };
    }
}
