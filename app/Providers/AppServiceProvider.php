<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Domain registrations are handled in respective Domain Service Providers
    }

    public function boot(): void
    {
        // Surface N+1 queries and silent attribute typos early (never in prod).
        Model::shouldBeStrict(! $this->app->isProduction());

        // Prohibit destructive database commands (migrate:fresh, db:wipe) in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Register ingress webhook rate limiter (60 requests per minute per IP)
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
