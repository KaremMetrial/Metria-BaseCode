<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Governance\Presentation\Http\Controllers\Api\V1\ApprovalController;
use Modules\Governance\Presentation\Http\Controllers\Api\V1\AuditLogController;
use Modules\Governance\Presentation\Http\Controllers\Api\V1\FeatureFlagController;
use Modules\Governance\Presentation\Http\Controllers\Api\V1\SettingsController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php — that must be repeated explicitly
// here (same reasoning as modules/Webhook/Presentation/routes/api.php).
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
        Route::prefix('governance')->name('governance.')->group(function () {

            // Settings Management
            Route::prefix('settings')->name('settings.')->group(function () {
                Route::middleware('permission:governance.settings.view')->group(function () {
                    Route::get('/', [SettingsController::class, 'index'])->name('index');
                    Route::get('/{key}', [SettingsController::class, 'show'])->name('show');
                });
                Route::middleware('permission:governance.settings.manage')->group(function () {
                    Route::put('/{key}', [SettingsController::class, 'update'])->name('update');
                    Route::delete('/{key}', [SettingsController::class, 'destroy'])->name('destroy');
                });
            });

            // Feature Flags
            Route::prefix('flags')->name('flags.')->group(function () {
                Route::get('/', [FeatureFlagController::class, 'index'])
                    ->middleware('permission:governance.flags.manage')
                    ->name('index');
                Route::get('/{name}', [FeatureFlagController::class, 'show'])->name('show');
                Route::put('/{name}', [FeatureFlagController::class, 'toggle'])
                    ->middleware('permission:governance.flags.manage')
                    ->name('toggle');
            });

            // Maker-Checker Approvals
            Route::prefix('approvals')->name('approvals.')->group(function () {
                Route::get('/', [ApprovalController::class, 'index'])
                    ->middleware('permission:governance.approvals.view')
                    ->name('index');
                Route::post('/{approvalRequest}/approve', [ApprovalController::class, 'approve'])
                    ->middleware('permission:governance.approvals.decide')
                    ->name('approve');
                Route::post('/{approvalRequest}/reject', [ApprovalController::class, 'reject'])
                    ->middleware('permission:governance.approvals.decide')
                    ->name('reject');
            });

            // Audit Logs
            Route::get('/audit-logs', [AuditLogController::class, 'index'])
                ->middleware('permission:governance.audit.view')
                ->name('audit.index');
        });
    });
