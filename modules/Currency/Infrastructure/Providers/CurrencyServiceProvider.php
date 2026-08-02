<?php

declare(strict_types=1);

namespace Modules\Currency\Infrastructure\Providers;

use Modules\Shared\Domain\Contracts\CurrencyRegistryResolver;
use Modules\Currency\Infrastructure\Console\Commands\SyncExchangeRates;
use Modules\Currency\Domain\Repositories\ExchangeRateRepositoryInterface;
use Modules\Currency\Domain\Models\Currency;
use Modules\Currency\Domain\Models\CurrencyExchangeRate;
use Modules\Currency\Presentation\Policies\CurrencyExchangeRatePolicy;
use Modules\Currency\Presentation\Policies\CurrencyPolicy;
use Modules\Currency\Infrastructure\Persistence\Repositories\ExchangeRateRepository;
use Modules\Currency\Infrastructure\Services\CurrencyRegistryResolverImpl;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Localization\LangPathRegistry;

class CurrencyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/currencies.php', 'currencies');

        LangPathRegistry::register(__DIR__.'/../Resources/lang');

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

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncExchangeRates::class,
            ]);
        }
    }
}
