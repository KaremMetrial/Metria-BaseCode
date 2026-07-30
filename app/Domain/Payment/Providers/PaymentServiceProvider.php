<?php

declare(strict_types=1);

namespace App\Domain\Payment\Providers;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Policies\PaymentPolicy;
use App\Domain\Payment\Services\PaymentManager;
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
        $this->app->singleton(PaymentManager::class, fn (\Illuminate\Contracts\Container\Container $app) => new PaymentManager($app));
    }

    public function boot(): void
    {
        Gate::policy(Payment::class, PaymentPolicy::class);

        EnumRegistry::register('payment_status', PaymentStatus::class);

        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
