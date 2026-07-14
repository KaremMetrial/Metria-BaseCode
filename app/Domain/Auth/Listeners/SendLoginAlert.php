<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Notifications\LoginAlertNotification;

class SendLoginAlert
{
    public function handle(object $event): void
    {
        if (! property_exists($event, 'user')) {
            return;
        }

        /** @var \App\Domain\Auth\Models\User|object|null $user */
        $user = $event->user;

        if (is_object($user) && method_exists($user, 'notify')) {
            $ip = request()->ip() ?: '127.0.0.1';
            $agent = request()->userAgent() ?: 'Unknown Device';
            $time = now()->toDateTimeString();

            /** @var \App\Domain\Auth\Models\User $user */
            $user->notify(new LoginAlertNotification($ip, $agent, $time));
        }
    }
}
