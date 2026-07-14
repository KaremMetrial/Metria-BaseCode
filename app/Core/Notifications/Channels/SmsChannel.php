<?php

declare(strict_types=1);

namespace App\Core\Notifications\Channels;

use App\Domain\Integration\Sms\SmsManager;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(protected readonly SmsManager $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        /** @var string|null $to */
        $to = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sms', $notification)
            : ($notifiable->phone ?? null);

        if (! $to) {
            return;
        }

        /** @var string $message */
        $message = $notification->toSms($notifiable);
        $this->sms->driver()->send($to, $message);
    }
}
