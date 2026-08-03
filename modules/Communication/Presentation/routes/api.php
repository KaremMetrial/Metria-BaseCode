<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Communication\Presentation\Http\Controllers\Api\V1\ConversationController;
use Modules\Communication\Presentation\Http\Controllers\Api\V1\MessageController;

Route::prefix('api/v1/communication')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:communication'])
    ->name('communication.')
    ->group(function (): void {
        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('/conversations', [ConversationController::class, 'store'])
            ->middleware('idempotent')
            ->name('conversations.store');
        Route::get('/conversations/{conversation}/sync', [ConversationController::class, 'sync'])
            ->name('conversations.sync');
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->middleware('idempotent')
            ->name('messages.store');
    });
