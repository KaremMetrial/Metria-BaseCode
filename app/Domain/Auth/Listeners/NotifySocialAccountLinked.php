<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\SocialIdentityLinked;
use App\Domain\Auth\Notifications\LoginAlertNotification;

class NotifySocialAccountLinked
{
    public function handle(SocialIdentityLinked $event): void
    {
        $user = $event->user;

        if (method_exists($user, 'notify')) {
            $ip = request()->ip() ?: '127.0.0.1';
            $agent = request()->userAgent() ?: 'Unknown Device';
            $time = now()->toDateTimeString();

            // Notify user about new OAuth link
            $user->notify(new LoginAlertNotification($ip, "Linked {$event->provider} on {$agent}", $time));
        }
    }
}
