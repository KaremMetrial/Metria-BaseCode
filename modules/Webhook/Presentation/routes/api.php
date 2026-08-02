<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Webhook\Presentation\Http\Controllers\Api\V1\WebhookEndpointController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...) applies
// automatically to routes/api.php — that must be repeated explicitly here.
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('webhook-endpoints')
            ->name('webhook-endpoints.')
            ->middleware('permission:webhooks.manage')
            ->group(function () {
                Route::get('/', [WebhookEndpointController::class, 'index'])->name('index');
                Route::post('/', [WebhookEndpointController::class, 'store'])->name('store');
                Route::put('/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('update');
                Route::delete('/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('destroy');
                Route::post('/{webhookEndpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('rotate');
            });
    });
