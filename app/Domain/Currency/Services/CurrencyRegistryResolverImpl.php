<?php

declare(strict_types=1);

namespace App\Domain\Currency\Services;

use App\Core\Support\CurrencyRegistryResolver;
use App\Domain\Currency\Models\Currency;
use Illuminate\Support\Facades\Cache;

class CurrencyRegistryResolverImpl implements CurrencyRegistryResolver
{
    /**
     * Resolve the number of minor units (decimal places) for a given ISO currency code.
     * Caches results to maintain zero-database-overhead for Money allocation.
     */
    public function minorUnitsFor(string $currency): int
    {
        $currency = strtoupper($currency);

        return Cache::remember("currency_minor_units_{$currency}", now()->addDays(7), function () use ($currency) {
            try {
                $dbCurrency = Currency::find($currency);
                if ($dbCurrency !== null) {
                    return $dbCurrency->minor_units;
                }
            } catch (\Throwable $e) {
                // Fallback to configuration during transient database outages or early bootstrap
            }

            // Fallback to configuration
            return (int) (config("payments.minor_units.{$currency}") ?? 2);
        });
    }

    /**
     * Invalidate the minor units cache for a given currency code.
     */
    public function invalidateCache(string $currency): void
    {
        Cache::forget('currency_minor_units_'.strtoupper($currency));
    }
}
