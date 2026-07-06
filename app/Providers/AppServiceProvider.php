<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Integration\Sms\SmsManager;
use App\Domain\Payment\PaymentManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class, fn ($app) => new PaymentManager($app));
        $this->app->singleton(SmsManager::class, fn ($app) => new SmsManager($app));
    }

    public function boot(): void
    {
        // Surface N+1 queries and silent attribute typos early (never in prod).
        Model::shouldBeStrict(! $this->app->isProduction());

        // Prohibit destructive database commands (migrate:fresh, db:wipe) in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
