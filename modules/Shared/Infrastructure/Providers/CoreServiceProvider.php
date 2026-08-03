<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Modules\Shared\Infrastructure\Events\EventBus;
use Modules\Shared\Infrastructure\Localization\LangPathRegistry;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;
use Modules\Shared\Presentation\Exceptions\ApiExceptionRenderer;
use Modules\Shared\Presentation\Http\Middleware\ForceJsonResponse;
use Modules\Shared\Presentation\Http\Middleware\IdempotencyMiddleware;
use Modules\Shared\Presentation\Http\Middleware\ResolveTenant;
use Modules\Shared\Presentation\Http\Middleware\SetLocale;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy.php', 'tenancy');

        LangPathRegistry::register(__DIR__.'/../Resources/lang');

        $this->app->singleton(TenantManager::class);
        $this->app->singleton(EventBus::class);
        $this->app->register(QueueTenantProvider::class);

        $this->registerModuleTranslations();
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerMiddleware();
        $this->registerExceptionHandling();

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }

    /**
     * Extends Laravel's default `translation.loader` binding so module-owned
     * lang directories (registered via LangPathRegistry) are also searched
     * for the plain `__('group.key')` syntax every existing call site uses.
     * Laravel's FileLoader natively supports multiple search paths for the
     * default namespace (its constructor already accepts an array) — it's
     * just never given more than the framework's own fallback path plus the
     * app's lang_path() out of the box. The closure reads the registry
     * lazily at resolution time, so it picks up every module's registered
     * path regardless of provider order, and reads the original loader's
     * existing paths via reflection rather than hardcoding them, so it
     * never silently drops Laravel's own built-in fallback path.
     */
    private function registerModuleTranslations(): void
    {
        $this->app->extend('translation.loader', function (FileLoader $loader, $app) {
            $property = new \ReflectionProperty(FileLoader::class, 'paths');
            $property->setAccessible(true);
            /** @var array<int, string> $existingPaths */
            $existingPaths = $property->getValue($loader);

            return new FileLoader($app['files'], array_merge($existingPaths, LangPathRegistry::all()));
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('realtime-internal', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }

    /**
     * Self-registers this module's middleware aliases, its two `api`-group
     * entries, and its priority-array position — previously done by hand in
     * bootstrap/app.php, which meant Shared couldn't be copied into another
     * Laravel app without also reverse-engineering that wiring by reading
     * this specific app's bootstrap file. Router exposes these as public
     * methods for exactly this purpose (the same technique packages like
     * Sanctum rely on).
     */
    private function registerMiddleware(): void
    {
        Route::aliasMiddleware('tenant', ResolveTenant::class);
        Route::aliasMiddleware('idempotent', IdempotencyMiddleware::class);

        // Two single-item prepends (unshift) applied in reverse order so the
        // final order matches the original Middleware::api(prepend: [...]):
        // ForceJsonResponse, SetLocale, then whatever was already there.
        Route::prependMiddlewareToGroup('api', SetLocale::class);
        Route::prependMiddlewareToGroup('api', ForceJsonResponse::class);

        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app->make('router');
        $priority = $router->middlewarePriority;

        if (! in_array(ForceJsonResponse::class, $priority, true)) {
            array_splice($priority, 0, 0, [ForceJsonResponse::class, SetLocale::class]);
        }

        if (! in_array(ResolveTenant::class, $priority, true)) {
            $anchor = array_search(SubstituteBindings::class, $priority, true);
            $insertAt = is_int($anchor) ? $anchor : count($priority);
            array_splice($priority, $insertAt, 0, [ResolveTenant::class]);
        }

        $router->middlewarePriority = $priority;
    }

    /**
     * Registers the API exception renderer directly on the Handler singleton.
     * Functionally identical to bootstrap/app.php's withExceptions() closure:
     * Illuminate\Foundation\Configuration\Exceptions::render() is a thin
     * wrapper over Handler::renderable() (confirmed in vendor source), so
     * calling renderable() here from the module's own provider produces the
     * same registration without requiring host bootstrap wiring.
     */
    private function registerExceptionHandling(): void
    {
        (new ApiExceptionRenderer)->register($this->app->make(ExceptionHandler::class));
    }
}
