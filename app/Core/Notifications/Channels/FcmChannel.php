<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\SendPushToUser;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(protected readonly SendPushToUser $sendPush) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        $this->sendPush->__invoke(
            $notifiable,
            $payload['title'],
            $payload['body'],
            $payload['data'] ?? []
        );
    }
}
