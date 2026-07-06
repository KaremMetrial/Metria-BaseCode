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

Broadcast::channel('users.{id}', function (User $user, string $id): bool {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('wallets.{wallet}', function (User $user, Wallet $wallet): bool {
    return (string) $wallet->user_id === (string) $user->id;
});

Broadcast::channel('payments.{payment}', function (User $user, Payment $payment): bool {
    return (string) $payment->user_id === (string) $user->id;
});
