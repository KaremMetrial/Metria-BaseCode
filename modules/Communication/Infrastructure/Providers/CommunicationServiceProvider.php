<?php

declare(strict_types=1);

namespace Modules\Communication\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Infrastructure\Repositories\TenantConversationRepository;
use Modules\Communication\Infrastructure\Support\MessageKindRegistry;
use Modules\Communication\Presentation\Policies\ConversationPolicy;

final class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessageKindRegistry::class);
        $this->app->singleton(TenantConversationRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);

        RateLimiter::for('communication', fn (Request $request) => Limit::perMinute(120)
            ->by(($request->user()?->getAuthIdentifier() ?: $request->ip()).'|'.$request->path()));

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
