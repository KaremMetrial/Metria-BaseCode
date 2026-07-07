<?php

declare(strict_types=1);

namespace App\Domain\Currency\Services;

use App\Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use App\Domain\Currency\Models\CurrencyExchangeRate;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

class ExchangeRateResolver
{
    public function __construct(
        protected ExchangeRateRepositoryInterface $repository
    ) {}

    /**
     * Resolve the active exchange rate for a given currency at a point in time.
     * Integrates cache lookups for high-performance retrieval.
     */
    public function resolveActiveRate(string $currencyCode, DateTimeInterface $at): ?CurrencyExchangeRate
    {
        $currencyCode = strtoupper($currencyCode);

        // If lookup time is close to current time, fetch from cache.
        $isCurrent = abs(time() - $at->getTimestamp()) < 60;

        if ($isCurrent) {
            $cacheKey = "currency_active_rate_{$currencyCode}";

            return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($currencyCode, $at) {
                return $this->repository->getActiveRate($currencyCode, $at);
            });
        }

        // Historical query
        return $this->repository->getActiveRate($currencyCode, $at);
    }

    /**
     * Invalidate the active rate cache for a given currency code.
     */
    public function invalidateCache(string $currencyCode): void
    {
        Cache::forget('currency_active_rate_'.strtoupper($currencyCode));
    }
}
