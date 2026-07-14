<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\PasswordResetSuccessfully;
use App\Domain\Auth\Notifications\LoginAlertNotification;

class NotifyPasswordChanged
{
    public function handle(PasswordResetSuccessfully $event): void
    {
        $user = $event->user;

        $ip = request()->ip() ?: '127.0.0.1';
        $agent = request()->userAgent() ?: 'Unknown Device';
        $time = now()->toDateTimeString();

        // Notify user about security change
        $user->notify(new LoginAlertNotification($ip, "Password Reset on {$agent}", $time));
    }
}
