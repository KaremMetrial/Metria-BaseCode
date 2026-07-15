<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LocaleResolver::class);
        $this->app->singleton(TranslationRegistry::class);

        $this->app->singleton('translation', function (\Illuminate\Contracts\Container\Container $app) {
            return new TranslationManager($app);
        });

        $this->app->alias('translation', TranslationManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
