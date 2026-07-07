<?php

declare(strict_types=1);

namespace App\Domain\Integration\Providers;

use App\Domain\Integration\Sms\SmsManager;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsManager::class, fn ($app) => new SmsManager($app));
    }
}
