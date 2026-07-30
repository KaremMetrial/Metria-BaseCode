<?php

declare(strict_types=1);

namespace App\Domain\Currency\Providers;

use App\Core\Contracts\CurrencyRegistryResolver;
use App\Domain\Currency\Console\Commands\SyncExchangeRates;
use App\Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\CurrencyExchangeRate;
use App\Domain\Currency\Policies\CurrencyExchangeRatePolicy;
use App\Domain\Currency\Policies\CurrencyPolicy;
use App\Domain\Currency\Repositories\ExchangeRateRepository;
use App\Domain\Currency\Services\CurrencyRegistryResolverImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CurrencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ExchangeRateRepositoryInterface::class,
            ExchangeRateRepository::class
        );

        $this->app->singleton(ExchangeRateProviderChain::class, function ($app) {
            $chain = new ExchangeRateProviderChain;
            $apiConfig = config('currencies.api', []);
            $chain->registerProvider('currency_exchange_api', new CurrencyExchangeApiProvider(is_array($apiConfig) ? $apiConfig : []));
            $chain->registerProvider('mock', new MockExchangeRateProvider);

            return $chain;
        });

        $this->app->singleton(
            CurrencyRegistryResolver::class,
            CurrencyRegistryResolverImpl::class
        );
    }

    public function boot(): void
    {
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(CurrencyExchangeRate::class, CurrencyExchangeRatePolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncExchangeRates::class,
            ]);
        }
    }
}
