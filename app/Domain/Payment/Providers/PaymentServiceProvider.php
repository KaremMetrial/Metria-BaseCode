<?php

declare(strict_types=1);

namespace App\Domain\Payment\Providers;

use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Policies\PaymentPolicy;
use App\Domain\Payment\Services\PaymentManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class, fn ($app) => new PaymentManager($app));
    }

    public function boot(): void
    {
        Gate::policy(Payment::class, PaymentPolicy::class);
    }
}
