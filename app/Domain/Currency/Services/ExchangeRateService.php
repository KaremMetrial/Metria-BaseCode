<?php

declare(strict_types=1);

namespace App\Domain\Currency\Services;

use App\Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use App\Domain\Currency\Models\CurrencyExchangeRate;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExchangeRateService
{
    public function __construct(
        protected ExchangeRateRepositoryInterface $repository,
        protected ExchangeRateResolver $resolver
    ) {}

    /**
     * Get the active exchange rate. Performs freshness check against stale threshold policy.
     */
    public function getActiveRate(string $currencyCode, DateTimeInterface $at): CurrencyExchangeRate
    {
        $rate = $this->resolver->resolveActiveRate($currencyCode, $at);

        if ($rate === null) {
            // Fallback: get the latest rate registered before or at the target time
            $rate = CurrencyExchangeRate::where('currency_code', strtoupper($currencyCode))
                ->where('effective_at', '<=', $at)
                ->orderBy('effective_at', 'desc')
                ->first();
        }

        if ($rate === null) {
            throw new DomainException(__('currency.exchange_rate_missing', ['currency' => $currencyCode]));
        }

        // Stale rate check
        $thresholdHoursVal = config('currencies.stale_rate_threshold_hours', 24);
        $thresholdHours = is_numeric($thresholdHoursVal) ? (int) $thresholdHoursVal : 24;
        $expirationWithGrace = Carbon::instance($rate->expires_at)->addHours($thresholdHours);

        if (Carbon::instance($at)->greaterThan($expirationWithGrace)) {
            throw new DomainException(__('currency.exchange_rate_stale', ['currency' => $currencyCode, 'expired_at' => $rate->expires_at->toIso8601String()]));
        }

        return $rate;
    }

    /**
     * Store/register a new exchange rate ensuring strict non-overlapping and contiguous validity windows.
     */
    public function registerRate(array $data): CurrencyExchangeRate
    {
        $codeVal = $data['currency_code'] ?? '';
        $currencyCode = strtoupper(is_string($codeVal) ? $codeVal : '');

        $effVal = $data['effective_at'] ?? null;
        if (! is_string($effVal) && ! $effVal instanceof DateTimeInterface && ! is_numeric($effVal)) {
            $effVal = 'now';
        }
        $effectiveAt = Carbon::parse($effVal);

        // Default expires_at to far future if not provided
        $expVal = $data['expires_at'] ?? null;
        if ($expVal !== null && ! is_string($expVal) && ! $expVal instanceof DateTimeInterface && ! is_numeric($expVal)) {
            $expVal = '2099-12-31 23:59:59';
        }
        $expiresAt = $expVal !== null ? Carbon::parse($expVal) : Carbon::parse('2099-12-31 23:59:59');

        return DB::transaction(function () use ($currencyCode, $effectiveAt, $expiresAt, $data) {
            // Guard: Manual overrides always take precedence over provider updates when locked.
            if (! ($data['is_manual'] ?? false)) {
                $hasLockedOverride = DB::table('currency_exchange_rates')
                    ->where('currency_code', $currencyCode)
                    ->where('is_locked', true)
                    ->where('effective_at', '<=', $effectiveAt)
                    ->where('expires_at', '>', $effectiveAt)
                    ->exists();

                if ($hasLockedOverride) {
                    throw new DomainException(__('currency.override_locked', ['currency' => $currencyCode]));
                }
            }

            // Find the active rate immediately preceding this new effective_at time
            $precedingRate = $this->repository->findLatestRateBefore($currencyCode, $effectiveAt);

            if ($precedingRate !== null) {
                // Invariant: Historical rate records are immutable.
                // We only update the expires_at of the preceding rate if it was going to expire AFTER the new effective_at.
                if ($precedingRate->expires_at->greaterThan($effectiveAt)) {
                    // Lock row to prevent concurrent updates
                    DB::table('currency_exchange_rates')
                        ->where('id', $precedingRate->id)
                        ->lockForUpdate()
                        ->update(['expires_at' => $effectiveAt]);
                }
            }

            // Find if there is a rate starting after our new effective_at
            $succeedingRate = $this->repository->findFirstRateAfter($currencyCode, $effectiveAt);
            if ($succeedingRate !== null) {
                // Cap the new rate's expires_at to the succeeding rate's effective_at to prevent overlap
                if ($expiresAt->greaterThan($succeedingRate->effective_at)) {
                    $expiresAt = Carbon::instance($succeedingRate->effective_at);
                }
            }

            // Store new rate
            $rateData = array_merge($data, [
                'currency_code' => $currencyCode,
                'effective_at' => $effectiveAt,
                'expires_at' => $expiresAt,
            ]);

            $newRate = $this->repository->store($rateData);

            // Invalidate cache
            $this->resolver->invalidateCache($currencyCode);

            return $newRate;
        });
    }
}
