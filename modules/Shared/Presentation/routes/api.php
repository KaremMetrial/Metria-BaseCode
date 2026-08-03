<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Shared\Presentation\Http\Controllers\EnumController;
use Modules\Shared\Presentation\Http\Controllers\HealthController;
use Modules\Shared\Presentation\Http\Controllers\Internal\RealtimeAuthorizationController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php — that must be repeated explicitly
// here (same reasoning as modules/Webhook/Presentation/routes/api.php).
// These two routes are public: they never sat behind auth/tenant/throttle,
// so no further middleware is added here.
Route::prefix('api/v1')
    ->middleware(['api'])
    ->group(function () {
        Route::get('/health', HealthController::class)->name('health');

        Route::prefix('enums')->name('enums.')->group(function () {
            Route::get('/', [EnumController::class, 'index'])->name('index');
            Route::get('/{key}', [EnumController::class, 'show'])->name('show');
        });
    });

// These endpoints are for the private Socket.IO service only. They authenticate
// the service with a signed request, not a browser session or tenant header.
Route::prefix('api/internal/realtime')
    ->middleware(['api', 'throttle:realtime-internal'])
    ->group(function () {
        Route::post('/authenticate', [RealtimeAuthorizationController::class, 'authenticate']);
        Route::post('/authorize-resource', [RealtimeAuthorizationController::class, 'authorizeResource']);
    });
