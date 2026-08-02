<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Presentation\Http\Controllers\Api\V1\AuthController;
use Modules\Auth\Presentation\Http\Controllers\Api\V1\OtpAuthController;
use Modules\Auth\Presentation\Http\Controllers\Api\V1\SocialAuthController;

// Registered via loadRoutesFrom(), so this file is outside the `api` prefix +
// `api` middleware group that bootstrap/app.php's withRouting(api: ...)
// applies automatically to routes/api.php — that must be repeated explicitly
// here (same reasoning as modules/Webhook/Presentation/routes/api.php).

// Authentication (Guest Throttled) — public, not behind auth:sanctum.
Route::prefix('api/v1')
    ->middleware(['api', 'throttle:auth'])
    ->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
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
    });

// User Profile — authenticated & tenant-scoped.
Route::prefix('api/v1')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function () {
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
        });
    });
