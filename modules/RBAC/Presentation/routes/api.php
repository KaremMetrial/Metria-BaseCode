<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\RBAC\Presentation\Http\Controllers\Api\V1\EffectivePermissionController;
use Modules\RBAC\Presentation\Http\Controllers\Api\V1\PermissionController;
use Modules\RBAC\Presentation\Http\Controllers\Api\V1\RoleController;
use Modules\RBAC\Presentation\Http\Controllers\Api\V1\RolePermissionController;
use Modules\RBAC\Presentation\Http\Controllers\Api\V1\UserRoleController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php, and outside routes/api_v1.php's
// enclosing groups — both must be repeated explicitly here (same reasoning
// as modules/Webhook/Presentation/routes/api.php).
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('rbac')->name('rbac.')->group(function () {
            Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

            Route::apiResource('roles', RoleController::class);

            Route::prefix('roles/{role}/permissions')->name('roles.permissions.')->group(function () {
                Route::get('/', [RolePermissionController::class, 'index'])->name('index');
                Route::post('/', [RolePermissionController::class, 'store'])->name('store');
                Route::put('/', [RolePermissionController::class, 'update'])->name('update');
                Route::delete('/', [RolePermissionController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('users/{user}')->name('users.')->group(function () {
                Route::get('/effective-permissions', [EffectivePermissionController::class, 'show'])->name('effective-permissions');

                Route::prefix('roles')->name('roles.')->group(function () {
                    Route::get('/', [UserRoleController::class, 'index'])->name('index');
                    Route::post('/', [UserRoleController::class, 'store'])->name('store');
                    Route::put('/', [UserRoleController::class, 'update'])->name('update');
                    Route::delete('/', [UserRoleController::class, 'destroy'])->name('destroy');
                });
            });
        });
    });
