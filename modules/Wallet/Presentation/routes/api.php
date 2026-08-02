<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Wallet\Presentation\Http\Controllers\Api\V1\WalletController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php — that must be repeated explicitly
// here (same reasoning as modules/Webhook/Presentation/routes/api.php).
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletController::class, 'show'])->name('show');
            Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');
        });
    });
