<?php

declare(strict_types=1);

namespace App\Domain\Currency\Providers;

use App\Domain\Currency\Contracts\ExchangeRateProviderInterface;
use Exception;

class MockExchangeRateProvider implements ExchangeRateProviderInterface
{
    protected array $rates = [
        'USD' => '48.25300000000000',
        'EUR' => '52.12000000000000',
        'BHD' => '128.00000000000000',
        'JPY' => '0.31000000000000',
    ];

    protected bool $shouldFail = false;

    public function getName(): string
    {
        return 'mock';
    }

    public function getVersion(): string
    {
        return '1.0';
    }

    public function setRate(string $currencyCode, string $rate): void
    {
        $this->rates[strtoupper($currencyCode)] = $rate;
    }

    public function setShouldFail(bool $fail): void
    {
        $this->shouldFail = $fail;
    }

    public function fetchRate(string $currencyCode): array
    {
        if ($this->shouldFail) {
            throw new Exception(__('currency.mock_connection_timeout'));
        }

        $currencyCode = strtoupper($currencyCode);

        if (! isset($this->rates[$currencyCode])) {
            throw new Exception(__('currency.mock_rate_missing', ['currency' => $currencyCode]));
        }


        $rate = $this->rates[$currencyCode];
        $payload = json_encode(['rate' => $rate, 'currency' => $currencyCode, 'timestamp' => time()]) ?: '';

        return [
            'rate' => $rate,
            'response_hash' => hash('sha256', $payload),
            'original_payload' => $payload,
            'request_id' => 'mock-req-'.uniqid(),
        ];
    }
}
