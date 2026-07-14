<?php

declare(strict_types=1);

namespace App\Domain\Currency\Providers;

use App\Domain\Currency\Contracts\ExchangeRateProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class ExchangeRateProviderChain
{
    /** @var array<string, ExchangeRateProviderInterface> */
    protected array $providers = [];

    /**
     * Register a provider driver into the chain.
     */
    public function registerProvider(string $name, ExchangeRateProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Traverse the chain sequentially to fetch rates.
     * Integrates automatic logging and fallbacks.
     */
    public function fetchRate(string $currencyCode): array
    {
        $primaryVal = config('currencies.providers.primary');
        $primary = is_string($primaryVal) ? $primaryVal : '';

        $failoversVal = config('currencies.providers.failovers', []);
        $failovers = is_array($failoversVal) ? array_filter($failoversVal, 'is_string') : [];

        $rawList = array_merge([$primary], $failovers);
        /** @var array<string> $orderedList */
        $orderedList = array_values(array_filter($rawList, fn ($v) => is_string($v) && $v !== ''));
        $errors = [];

        foreach ($orderedList as $providerName) {
            if (! isset($this->providers[$providerName])) {
                continue;
            }

            try {
                $rateData = $this->providers[$providerName]->fetchRate($currencyCode);
                $rateData['provider_name'] = $this->providers[$providerName]->getName();
                $rateData['provider_version'] = $this->providers[$providerName]->getVersion();

                return $rateData;
            } catch (Exception $e) {
                $errors[$providerName] = $e->getMessage();
                Log::warning("Exchange rate provider '{$providerName}' failed syncing {$currencyCode}: ".$e->getMessage());
            }
        }

        throw new Exception("All exchange rate providers failed to fetch rate for {$currencyCode}. Trace: ".json_encode($errors));
    }
}
