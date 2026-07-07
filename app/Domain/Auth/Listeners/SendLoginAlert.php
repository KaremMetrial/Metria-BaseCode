<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Notifications\LoginAlertNotification;

class SendLoginAlert
{
    public function handle(object $event): void
    {
        $user = $event->user;

        if (method_exists($user, 'notify')) {
            $ip = request()->ip() ?: '127.0.0.1';
            $agent = request()->userAgent() ?: 'Unknown Device';
            $time = now()->toDateTimeString();

            $user->notify(new LoginAlertNotification($ip, $agent, $time));
        }
    }
}
