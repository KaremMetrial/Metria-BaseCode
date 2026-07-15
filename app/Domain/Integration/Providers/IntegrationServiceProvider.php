<?php

declare(strict_types=1);

namespace App\Domain\Integration\Providers;

use App\Domain\Auth\Contracts\OAuthConfigurationRepositoryInterface;
use App\Domain\Integration\Models\OAuthProvider;
use App\Domain\Integration\Policies\OAuthProviderPolicy;
use App\Domain\Integration\Repositories\DatabaseOAuthConfigurationRepository;
use App\Domain\Integration\Sms\SmsManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsManager::class, fn (\Illuminate\Contracts\Container\Container $app) => new SmsManager($app));
        $this->app->bind(
            OAuthConfigurationRepositoryInterface::class,
            DatabaseOAuthConfigurationRepository::class
        );
    }

    public function boot(): void
    {
        Gate::policy(OAuthProvider::class, OAuthProviderPolicy::class);
    }
}
