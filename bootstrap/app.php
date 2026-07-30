<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

// Module-owned middleware (aliases, `api`-group entries, priority ordering)
// and exception rendering are no longer wired here — each module registers
// its own from its Service Provider (see Modules\Shared\Infrastructure\
// Providers\CoreServiceProvider). Only genuinely host/framework-level
// wiring — the Spatie Permission middleware aliases (not auto-registered by
// that package) and the base Sanctum/authorization priority order — belongs
// in this file.
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->priority([
            Authenticate::class,
            EnsureFrontendRequestsAreStateful::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    // withExceptions() with no callback still performs the framework's own
    // ExceptionHandler::class -> Handler::class container binding (required
    // for exception handling to work at all) without registering any
    // host-specific renderers — those are added by CoreServiceProvider.
    ->withExceptions()
    ->create();
