<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Media\Presentation\Http\Controllers\Api\V1\MediaController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php — that must be repeated explicitly
// here (same reasoning as modules/Webhook/Presentation/routes/api.php).
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('media')->name('media.')->group(function () {
            Route::post('/presign', [MediaController::class, 'presign'])->name('presign');
            Route::post('/{media}/confirm', [MediaController::class, 'confirm'])->name('confirm');
            Route::get('/{media}/download', [MediaController::class, 'download'])->name('download');
        });
    });
