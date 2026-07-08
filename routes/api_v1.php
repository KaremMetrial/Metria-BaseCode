<?php

declare(strict_types=1);

use App\Domain\Auth\Http\Controllers\Api\V1\AuthController;
use App\Domain\Auth\Http\Controllers\Api\V1\OtpAuthController;
use App\Domain\Auth\Http\Controllers\Api\V1\SocialAuthController;
use App\Domain\Integration\Http\Controllers\Api\V1\OAuthProviderController;
use App\Domain\Governance\Http\Controllers\Api\V1\ApprovalController;
use App\Domain\Governance\Http\Controllers\Api\V1\AuditLogController;
use App\Domain\Governance\Http\Controllers\Api\V1\FeatureFlagController;
use App\Domain\Governance\Http\Controllers\Api\V1\SettingsController;
use App\Domain\Payment\Http\Controllers\Api\V1\PaymentController;
use App\Domain\Payment\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Domain\System\Http\Controllers\Api\V1\EnumController;
use App\Domain\System\Http\Controllers\Api\V1\HealthController;
use App\Domain\Territory\Http\Controllers\Api\V1\TerritoryController;
use App\Domain\Wallet\Http\Controllers\Api\V1\WalletController;
use App\Domain\Webhook\Http\Controllers\Api\V1\WebhookEndpointController;
use App\Domain\Media\Http\Controllers\Api\V1\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// System Deep Health Check
Route::get('/health', HealthController::class)->name('health');

// System Enums (Frontend UI Support)
Route::prefix('enums')->name('enums.')->group(function () {
    Route::get('/', [EnumController::class, 'index'])->name('index');
    Route::get('/{key}', [EnumController::class, 'show'])->name('show');
});

// Territories & Logistics Zones
Route::prefix('territories')->name('territories.')->group(function () {
    Route::get('/countries', [TerritoryController::class, 'countries'])->name('countries');
    Route::get('/countries/{country}/governorates', [TerritoryController::class, 'governorates'])->name('governorates');
    Route::get('/governorates/{governorate}/cities', [TerritoryController::class, 'cities'])->name('cities');
    Route::get('/cities/{city}/districts', [TerritoryController::class, 'districts'])->name('districts');
    Route::get('/zones', [TerritoryController::class, 'zones'])->name('zones');
    Route::post('/zones/resolve', [TerritoryController::class, 'resolveZone'])->name('zones.resolve');
});

// Authentication (Guest Throttled)
Route::middleware('throttle:auth')->prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/mfa/verify', [AuthController::class, 'verifyMfa'])->name('mfa.verify');
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');

    Route::prefix('social')->name('social.')->group(function () {
        Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('redirect');
        Route::post('/{provider}/callback', [SocialAuthController::class, 'callback'])->name('callback');
    });

    Route::prefix('otp')->name('otp.')->group(function () {
        Route::post('/send', [OtpAuthController::class, 'send'])->name('send');
        Route::post('/login', [OtpAuthController::class, 'login'])->name('login');
        Route::post('/register', [OtpAuthController::class, 'register'])->name('register');
    });
});

// Payment Gateway Callbacks (Signed Webhooks)
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->whereIn('gateway', ['stripe', 'paymob', 'fawry', 'paytabs'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');

/*
|--------------------------------------------------------------------------
| Authenticated & Tenant-Scoped Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {

    // User Profile
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/fcm-token', [AuthController::class, 'updateFcmToken'])->name('fcm-token');

        Route::get('/sessions', [AuthController::class, 'sessions'])->name('sessions.index');
        Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession'])->name('sessions.destroy');

        Route::prefix('mfa')->name('mfa.')->group(function () {
            Route::post('/enable', [AuthController::class, 'enableMfa'])->name('enable');
            Route::post('/confirm', [AuthController::class, 'confirmMfa'])->name('confirm');
            Route::post('/disable', [AuthController::class, 'disableMfa'])->name('disable');
        });

        Route::prefix('social')->name('social.')->group(function () {
            Route::post('/{provider}/link', [SocialAuthController::class, 'link'])->name('link');
            Route::delete('/{provider}/unlink', [SocialAuthController::class, 'unlink'])->name('unlink');
        });

        Route::apiResource('oauth-providers', OAuthProviderController::class);
    });

    // Wallet Ledger
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'show'])->name('show');
        Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');
    });

    // Payment Processing
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

    // Governance & Administration
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

    // Outgoing Webhook Endpoints
    Route::prefix('webhook-endpoints')->name('webhook-endpoints.')->middleware('permission:webhooks.manage')->group(function () {
        Route::get('/', [WebhookEndpointController::class, 'index'])->name('index');
        Route::post('/', [WebhookEndpointController::class, 'store'])->name('store');
        Route::put('/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('update');
        Route::delete('/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('destroy');
        Route::post('/{webhookEndpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('rotate');
    });

    // Media Upload & Download
    Route::prefix('media')->name('media.')->group(function () {
        Route::post('/presign', [MediaController::class, 'presign'])->name('presign');
        Route::post('/{media}/confirm', [MediaController::class, 'confirm'])->name('confirm');
        Route::get('/{media}/download', [MediaController::class, 'download'])->name('download');
    });
});
