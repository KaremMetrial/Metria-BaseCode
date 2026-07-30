<?php

declare(strict_types=1);

namespace Modules\Webhook\Infrastructure\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Localization\LangPathRegistry;
use Modules\Webhook\Domain\Models\WebhookEndpoint;
use Modules\Webhook\Infrastructure\Console\Commands\PublishOutboxMessages;
use Modules\Webhook\Presentation\Policies\WebhookEndpointPolicy;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webhook.php', 'webhook');

        LangPathRegistry::register(__DIR__.'/../Resources/lang');
    }

    public function boot(): void
    {
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishOutboxMessages::class,
            ]);
        }
    }
}
