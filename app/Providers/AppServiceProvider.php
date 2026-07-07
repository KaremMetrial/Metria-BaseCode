<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Support\CurrencyRegistryResolver;
use App\Domain\Currency\Contracts\ExchangeRateRepositoryInterface;
use App\Domain\Currency\Providers\ExchangeRateProviderChain;
use App\Domain\Currency\Providers\MockExchangeRateProvider;
use App\Domain\Currency\Repositories\ExchangeRateRepository;
use App\Domain\Currency\Services\CurrencyRegistryResolverImpl;
use App\Domain\Integration\Sms\SmsManager;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\PaymentManager;
use App\Domain\Payment\Policies\PaymentPolicy;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Policies\WalletPolicy;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Domain\Webhook\Policies\WebhookEndpointPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class, fn ($app) => new PaymentManager($app));
        $this->app->singleton(SmsManager::class, fn ($app) => new SmsManager($app));

        // Currency Domain Bindings
        $this->app->singleton(
            ExchangeRateRepositoryInterface::class,
            ExchangeRateRepository::class
        );

        $this->app->singleton(ExchangeRateProviderChain::class, function ($app) {
            $chain = new ExchangeRateProviderChain;
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
        // Surface N+1 queries and silent attribute typos early (never in prod).
        Model::shouldBeStrict(! $this->app->isProduction());

        // Prohibit destructive database commands (migrate:fresh, db:wipe) in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Register domain policies
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        // Register ingress webhook rate limiter (60 requests per minute per IP)
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
