<?php

declare(strict_types=1);

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Models\Payment;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for user-specific real-time notifications (profile updates, alerts)
Broadcast::channel('users.{id}', function (User $user, string $id): bool {
    return (string) $user->id === (string) $id;
});

// Private channel for wallet balance & escrow ledger events
Broadcast::channel('wallets.{wallet}', function (User $user, Wallet $wallet): bool {
    return (string) $wallet->user_id === (string) $user->id;
});

// Private channel for payment gateway & transaction status updates
Broadcast::channel('payments.{payment}', function (User $user, Payment $payment): bool {
    return (string) $payment->user_id === (string) $user->id;
});

/*
|--------------------------------------------------------------------------
| Presence Channels (Enterprise Collaboration & Dispatch)
|--------------------------------------------------------------------------
|
| Presence channels allow real-time awareness of online users.
| In Metrial Base Code, we use presence channels for:
| 1. Territorial Zone Monitoring: Real-time awareness of active operators/managers in a zone.
| 2. Customer Support Chat: Active ticket viewers and typing indicators.
| 3. Live Trip Monitoring: Real-time connection monitoring between driver & rider.
|
*/

Broadcast::channel('territories.zone.{zoneId}', function (User $user, string $zoneId): ?array {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});

Broadcast::channel('support.ticket.{ticketId}', function (User $user, string $ticketId): ?array {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
