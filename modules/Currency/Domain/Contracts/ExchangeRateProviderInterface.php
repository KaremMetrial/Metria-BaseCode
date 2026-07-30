<?php

declare(strict_types=1);

namespace App\Domain\Currency\Contracts;

interface ExchangeRateProviderInterface
{
    /**
     * Get the identifier name of this provider.
     */
    public function getName(): string;

    /**
     * Get the version of this provider driver.
     */
    public function getVersion(): string;

    /**
     * Fetch the exchange rate for a currency code to base currency.
     * Returns a metadata array:
     * [
     *     'rate' => (string) rate value,
     *     'response_hash' => (string) 64-char hash of the raw response,
     *     'original_payload' => (string) raw JSON payload,
     *     'request_id' => (string) unique ID for trace auditing
     * ]
     *
     * @throws \Exception If provider API fails, times out, or returns invalid structure.
     */
    public function fetchRate(string $currencyCode): array;
}
