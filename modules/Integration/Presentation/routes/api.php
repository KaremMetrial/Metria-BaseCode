<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Integration\Presentation\Http\Controllers\Api\V1\OAuthProviderController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php, and outside routes/api_v1.php's
// enclosing groups — both must be repeated explicitly here (same reasoning
// as modules/Webhook/Presentation/routes/api.php). Nested under `auth.` to
// match the original route names/URIs exactly (api/v1/auth/oauth-providers).
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::apiResource('oauth-providers', OAuthProviderController::class);
        });
    });
