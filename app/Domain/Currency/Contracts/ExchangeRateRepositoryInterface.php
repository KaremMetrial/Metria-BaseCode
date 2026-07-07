<?php

declare(strict_types=1);

namespace App\Domain\Currency\Contracts;

use App\Domain\Currency\Models\CurrencyExchangeRate;
use DateTimeInterface;

interface ExchangeRateRepositoryInterface
{
    /**
     * Retrieve the active exchange rate for a currency at a specific point in time.
     */
    public function getActiveRate(string $currencyCode, DateTimeInterface $at): ?CurrencyExchangeRate;

    /**
     * Store a new exchange rate entry in database.
     */
    public function store(array $data): CurrencyExchangeRate;

    /**
     * Update the expiration timestamp of an exchange rate record.
     */
    public function updateExpiresAt(string $id, DateTimeInterface $expiresAt): void;

    /**
     * Find the latest rate for a currency that has an effective_at date strictly before the target date.
     */
    public function findLatestRateBefore(string $currencyCode, DateTimeInterface $effectiveAt): ?CurrencyExchangeRate;

    /**
     * Find the first rate for a currency that has an effective_at date strictly after the target date.
     */
    public function findFirstRateAfter(string $currencyCode, DateTimeInterface $effectiveAt): ?CurrencyExchangeRate;
}
