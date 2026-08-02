<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Territory\Presentation\Http\Controllers\Api\V1\TerritoryController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php — that must be repeated explicitly
// here (same reasoning as modules/Webhook/Presentation/routes/api.php). Public, but rate-limited.
Route::prefix('api/v1')
    ->middleware(['api'])
    ->group(function () {
        Route::prefix('territories')->middleware('throttle:60,1')->name('territories.')->group(function () {
            Route::get('/countries', [TerritoryController::class, 'countries'])->name('countries');
            Route::get('/countries/{country}/governorates', [TerritoryController::class, 'governorates'])->name('governorates');
            Route::get('/governorates/{governorate}/cities', [TerritoryController::class, 'cities'])->name('cities');
            Route::get('/cities/{city}/districts', [TerritoryController::class, 'districts'])->name('districts');
            Route::get('/zones', [TerritoryController::class, 'zones'])->name('zones');
            Route::post('/zones/resolve', [TerritoryController::class, 'resolveZone'])->name('zones.resolve');
        });
    });
