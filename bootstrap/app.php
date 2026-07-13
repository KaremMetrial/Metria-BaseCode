<?php

use App\Core\Exceptions\ApiExceptionRenderer;
use App\Core\Http\Middleware\ForceJsonResponse;
use App\Core\Http\Middleware\IdempotencyMiddleware;
use App\Core\Http\Middleware\SetLocale;
use App\Core\Tenancy\ResolveTenant;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'idempotent' => IdempotencyMiddleware::class,
            'tenant' => ResolveTenant::class,
        ]);

        $middleware->priority([
            ForceJsonResponse::class,
            SetLocale::class,
            Authenticate::class,
            EnsureFrontendRequestsAreStateful::class,
            ResolveTenant::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        (new ApiExceptionRenderer)->register($exceptions);
    })->create();
