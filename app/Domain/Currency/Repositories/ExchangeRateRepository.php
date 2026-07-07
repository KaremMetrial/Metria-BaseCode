<?php

declare(strict_types=1);

namespace App\Domain\Currency\Repositories;

use App\Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use App\Domain\Currency\Models\CurrencyExchangeRate;
use DateTimeInterface;

class ExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    public function getActiveRate(string $currencyCode, DateTimeInterface $at): ?CurrencyExchangeRate
    {
        return CurrencyExchangeRate::where('currency_code', strtoupper($currencyCode))
            ->where('effective_at', '<=', $at)
            ->where('expires_at', '>', $at)
            ->first();
    }

    public function store(array $data): CurrencyExchangeRate
    {
        return CurrencyExchangeRate::create($data);
    }

    public function updateExpiresAt(string $id, DateTimeInterface $expiresAt): void
    {
        CurrencyExchangeRate::where('id', $id)->update([
            'expires_at' => $expiresAt,
        ]);
    }

    public function findLatestRateBefore(string $currencyCode, DateTimeInterface $effectiveAt): ?CurrencyExchangeRate
    {
        return CurrencyExchangeRate::where('currency_code', strtoupper($currencyCode))
            ->where('effective_at', '<', $effectiveAt)
            ->orderBy('effective_at', 'desc')
            ->first();
    }

    public function findFirstRateAfter(string $currencyCode, DateTimeInterface $effectiveAt): ?CurrencyExchangeRate
    {
        return CurrencyExchangeRate::where('currency_code', strtoupper($currencyCode))
            ->where('effective_at', '>', $effectiveAt)
            ->orderBy('effective_at', 'asc')
            ->first();
    }
}
