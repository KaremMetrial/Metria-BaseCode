<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Providers;

use App\Domain\Webhook\Console\Commands\PublishOutboxMessages;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Domain\Webhook\Policies\WebhookEndpointPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishOutboxMessages::class,
            ]);
        }
    }
}
