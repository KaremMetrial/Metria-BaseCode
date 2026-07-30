<?php

declare(strict_types=1);

namespace Modules\Currency\Infrastructure\Services;

use Modules\Shared\Domain\Contracts\CurrencyRegistryResolver;
use Modules\Currency\Domain\Models\Currency;
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

            // Fallback to configuration — payments.minor_units is the still-
            // unmigrated Payment domain's config (TODO: update when Payment
            // module lands). Accepted transitional coupling: this is a
            // last-resort fallback behind the DB-backed lookup above.
            $minorUnitsVal = config("payments.minor_units.{$currency}");
            return is_numeric($minorUnitsVal) ? (int) $minorUnitsVal : 2;
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
