<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Governance\ApprovalController;
use App\Http\Controllers\Api\V1\Governance\AuditLogController;
use App\Http\Controllers\Api\V1\Governance\FeatureFlagController;
use App\Http\Controllers\Api\V1\Governance\SettingsController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Payment\PaymentWebhookController;
use App\Http\Controllers\Api\V1\System\EnumController;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Http\Controllers\Api\V1\Territory\TerritoryController;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use App\Http\Controllers\Api\V1\Webhook\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class)->name('health');

/*
|--------------------------------------------------------------------------
| System Enums (Frontend UI Support)
|--------------------------------------------------------------------------
*/
Route::prefix('enums')->name('enums.')->group(function () {
    Route::get('/', [EnumController::class, 'index'])->name('index');
    Route::get('/{key}', [EnumController::class, 'show'])->name('show');
});

/*
|--------------------------------------------------------------------------
| Territories & Logistics Zones
|--------------------------------------------------------------------------
*/
Route::prefix('territories')->name('territories.')->group(function () {
    Route::get('/countries', [TerritoryController::class, 'countries'])->name('countries');
    Route::get('/countries/{country}/governorates', [TerritoryController::class, 'governorates'])->name('governorates');
    Route::get('/governorates/{governorate}/cities', [TerritoryController::class, 'cities'])->name('cities');
    Route::get('/cities/{city}/districts', [TerritoryController::class, 'districts'])->name('districts');
    Route::get('/zones', [TerritoryController::class, 'zones'])->name('zones');
    Route::post('/zones/resolve', [TerritoryController::class, 'resolveZone'])->name('zones.resolve');
});

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
});

// Gateway callbacks — authenticated by signature, not by session/token.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->whereIn('gateway', ['stripe', 'paymob', 'fawry', 'paytabs'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');

/*
|--------------------------------------------------------------------------
| Authenticated (optionally tenant-scoped via X-Tenant header)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Wallet (own)
    Route::get('/wallet', [WalletController::class, 'show'])->name('wallet.show');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware(['idempotent', 'throttle:payments'])
        ->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])
        ->middleware('permission:payments.refund')
        ->name('payments.refund');

    /*
    |----------------------------------------------------------------------
    | Governance / administration (permission-guarded)
    |----------------------------------------------------------------------
    */

    Route::prefix('governance')->name('governance.')->group(function () {

        Route::middleware('permission:governance.settings.view')->group(function () {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::get('/settings/{key}', [SettingsController::class, 'show'])->name('settings.show');
        });

        Route::middleware('permission:governance.settings.manage')->group(function () {
            Route::put('/settings/{key}', [SettingsController::class, 'update'])->name('settings.update');
            Route::delete('/settings/{key}', [SettingsController::class, 'destroy'])->name('settings.destroy');
        });

        Route::get('/flags', [FeatureFlagController::class, 'index'])
            ->middleware('permission:governance.flags.manage')->name('flags.index');
        Route::get('/flags/{name}', [FeatureFlagController::class, 'show'])->name('flags.show');
        Route::put('/flags/{name}', [FeatureFlagController::class, 'toggle'])
            ->middleware('permission:governance.flags.manage')->name('flags.toggle');

        Route::get('/approvals', [ApprovalController::class, 'index'])
            ->middleware('permission:governance.approvals.view')->name('approvals.index');
        Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])
            ->middleware('permission:governance.approvals.decide')->name('approvals.approve');
        Route::post('/approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])
            ->middleware('permission:governance.approvals.decide')->name('approvals.reject');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:governance.audit.view')->name('audit.index');
    });

    // Outgoing webhook endpoints (consumer registrations)
    Route::middleware('permission:webhooks.manage')->group(function () {
        Route::get('/webhook-endpoints', [WebhookEndpointController::class, 'index'])->name('webhook-endpoints.index');
        Route::post('/webhook-endpoints', [WebhookEndpointController::class, 'store'])->name('webhook-endpoints.store');
        Route::put('/webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('webhook-endpoints.update');
        Route::delete('/webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
        Route::post('/webhook-endpoints/{webhookEndpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhook-endpoints.rotate');
    });
});
