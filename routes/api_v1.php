<?php

declare(strict_types=1);

use App\Domain\Auth\Http\Controllers\Api\V1\AuthController;
use App\Domain\Auth\Http\Controllers\Api\V1\OtpAuthController;
use App\Domain\Auth\Http\Controllers\Api\V1\SocialAuthController;
use Modules\Media\Presentation\Http\Controllers\Api\V1\MediaController;
use Modules\Territory\Presentation\Http\Controllers\Api\V1\TerritoryController;
use Modules\Wallet\Presentation\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Health and Enum routes moved to modules/Shared/Presentation/routes/api.php,
// self-registered by Modules\Shared\Infrastructure\Providers\CoreServiceProvider.

// Territories & Logistics Zones (Public but rate-limited)
Route::prefix('territories')->middleware('throttle:60,1')->name('territories.')->group(function () {
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

// Payment routes (webhook callback + processing) moved to
// modules/Payment/Presentation/routes/api.php, self-registered by
// Modules\Payment\Infrastructure\Providers\PaymentServiceProvider.

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

        // oauth-providers moved to modules/Integration/Presentation/routes/api.php,
        // self-registered by Modules\Integration\Infrastructure\Providers\IntegrationServiceProvider.
    });

    // Wallet Ledger
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'show'])->name('show');
        Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');
    });

    // Governance routes moved to modules/Governance/Presentation/routes/api.php,
    // self-registered by Modules\Governance\Infrastructure\Providers\GovernanceServiceProvider.

    // RBAC routes moved to modules/RBAC/Presentation/routes/api.php,
    // self-registered by Modules\RBAC\Infrastructure\Providers\RbacServiceProvider.

    // Media Upload & Download
    Route::prefix('media')->name('media.')->group(function () {
        Route::post('/presign', [MediaController::class, 'presign'])->name('presign');
        Route::post('/{media}/confirm', [MediaController::class, 'confirm'])->name('confirm');
        Route::get('/{media}/download', [MediaController::class, 'download'])->name('download');
    });
});
