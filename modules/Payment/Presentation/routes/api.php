<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Payment\Presentation\Http\Controllers\Api\V1\PaymentController;
use Modules\Payment\Presentation\Http\Controllers\Api\V1\PaymentWebhookController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php, and outside routes/api_v1.php's
// enclosing groups — both must be repeated explicitly here (same reasoning
// as modules/Webhook/Presentation/routes/api.php).

// Payment Gateway Callbacks (Signed Webhooks) — public, no auth:sanctum.
Route::prefix('api/v1')
    ->middleware(['api'])
    ->group(function () {
        Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
            ->whereIn('gateway', ['stripe', 'paymob', 'fawry', 'paytabs'])
            ->middleware('throttle:webhooks')
            ->name('webhooks.payments');
    });

// Payment Processing — authenticated & tenant-scoped.
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('/', [PaymentController::class, 'store'])
                ->middleware(['idempotent', 'throttle:payments'])
                ->name('store');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
            Route::post('/{payment}/refund', [PaymentController::class, 'refund'])
                ->middleware('permission:payments.refund')
                ->name('refund');
        });
    });
