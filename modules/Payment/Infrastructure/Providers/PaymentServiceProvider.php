<?php

declare(strict_types=1);

namespace Modules\Payment\Infrastructure\Providers;

use Modules\Payment\Domain\Enums\PaymentStatus;
use Modules\Payment\Domain\Models\Payment;
use Modules\Payment\Presentation\Policies\PaymentPolicy;
use Modules\Payment\Infrastructure\Services\PaymentManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\Support\EnumRegistry;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payments.php', 'payments');

        $this->app->singleton(PaymentManager::class, fn (\Illuminate\Contracts\Container\Container $app) => new PaymentManager($app));
    }

    public function boot(): void
    {
        Gate::policy(Payment::class, PaymentPolicy::class);

        EnumRegistry::register('payment_status', PaymentStatus::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
