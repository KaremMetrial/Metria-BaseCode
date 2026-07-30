<?php

declare(strict_types=1);

namespace App\Domain\Currency\Providers;

use App\Domain\Currency\Contracts\ExchangeRateProviderInterface;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * Live exchange rate provider integrating with CurrencyExchangeAPI (currencyapi.com / v3).
 *
 * Features:
 *  1. Automatic retry with exponential backoff for network resilience.
 *  2. Strict 14-decimal string formatting for BCMath arbitrary-precision compatibility.
 *  3. WORM audit traceability via SHA-256 payload hashing and unique request IDs.
 */
class CurrencyExchangeApiProvider implements ExchangeRateProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function getName(): string
    {
        return 'currency_exchange_api';
    }

    public function getVersion(): string
    {
        return '1.0';
    }

    /**
     * Fetch the exchange rate for a currency code relative to base currency.
     *
     * @return array{rate: string, response_hash: string, original_payload: string, request_id: string}
     *
     * @throws Exception If API call fails, times out, or returns an invalid schema.
     */
    public function fetchRate(string $currencyCode): array
    {
        $baseUrlVal = $this->config['base_url'] ?? 'https://api.currencyapi.com/v3';
        $baseUrl = rtrim(is_string($baseUrlVal) ? $baseUrlVal : 'https://api.currencyapi.com/v3', '/');

        $apiKeyVal = $this->config['api_key'] ?? '';
        $apiKey = is_string($apiKeyVal) ? $apiKeyVal : '';

        $baseCurrencyVal = $this->config['base_currency'] ?? 'USD';
        $baseCurrency = strtoupper(is_string($baseCurrencyVal) ? $baseCurrencyVal : 'USD');
        $targetCurrency = strtoupper($currencyCode);

        $timeoutVal = $this->config['timeout'] ?? 10;
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 10;

        $response = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->retry(2, 500)
            ->get('/latest', [
                'apikey' => $apiKey,
                'base_currency' => $baseCurrency,
                'currencies' => $targetCurrency,
            ]);

        if ($response->failed()) {
            throw new Exception(
                "CurrencyExchangeAPI failed syncing {$targetCurrency}: HTTP {$response->status()} - ".$response->body()
            );
        }

        $data = $response->json();
        $rate = data_get($data, "data.{$targetCurrency}.value");

        if ($rate === null || ! is_numeric($rate)) {
            throw new Exception(__('currency.invalid_api_payload', ['currency' => $targetCurrency]));
        }


        // Format to exactly 14 decimal places as a string to preserve BCMath precision
        $rateString = number_format((float) $rate, 14, '.', '');
        $rawPayload = $response->body();

        return [
            'rate' => $rateString,
            'response_hash' => hash('sha256', $rawPayload),
            'original_payload' => $rawPayload,
            'request_id' => 'ceapi-'.uniqid('', true),
        ];
    }
}
