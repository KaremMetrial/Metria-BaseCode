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

        $this->app->register(CoreServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
    }
}
