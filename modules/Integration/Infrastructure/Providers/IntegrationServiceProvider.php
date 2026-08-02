<?php

declare(strict_types=1);

namespace Modules\Integration\Infrastructure\Providers;

use Modules\Auth\Domain\Contracts\OAuthConfigurationRepositoryInterface;
use Modules\Integration\Domain\Models\OAuthProvider;
use Modules\Integration\Presentation\Policies\OAuthProviderPolicy;
use Modules\Integration\Infrastructure\Repositories\DatabaseOAuthConfigurationRepository;
use Modules\Integration\Infrastructure\Sms\SmsManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/integrations.php', 'integrations');

        $this->app->singleton(SmsManager::class, fn (\Illuminate\Contracts\Container\Container $app) => new SmsManager($app));
        $this->app->bind(
            OAuthConfigurationRepositoryInterface::class,
            DatabaseOAuthConfigurationRepository::class
        );
    }

    public function boot(): void
    {
        Gate::policy(OAuthProvider::class, OAuthProviderPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
