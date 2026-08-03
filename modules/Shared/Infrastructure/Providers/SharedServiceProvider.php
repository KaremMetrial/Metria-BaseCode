<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Translation\TranslationServiceProvider;

/**
 * Entry point for the Shared Kernel module. Composes the two previously
 * separate providers (Core's tenancy/event-bus/rate-limiting bootstrap and
 * the Translation pipeline) rather than merging their unrelated boot
 * sequences into one class, and merges the module's own config file so
 * `config('core.*')` keeps resolving after config/core.php moved here.
 */
class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/core.php', 'core');
        $this->mergeConfigFrom(__DIR__.'/../config/realtime.php', 'realtime');

        // Laravel's normal Redis connection prefixes all command keys. That is
        // correct for cache/session isolation but breaks the fixed pub/sub
        // channel contract consumed by the standalone realtime service.
        // Register a cloned, explicitly unprefixed connection before the
        // publisher can resolve the Redis manager.
        $defaultRedis = (array) config('database.redis.default', []);
        config()->set('database.redis.realtime', array_replace($defaultRedis, ['prefix' => '']));

        $this->app->register(CoreServiceProvider::class);
        $this->app->register(RealtimeServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
    }
}
