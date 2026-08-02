<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Notifications\Channels;

// TODO(Integration migration): reverting an earlier attempt to depend on a
// Shared SmsProvider contract instead of the concrete SmsManager — an
// existing test (tests/Feature/Auth/NotificationFlowTest.php) mocks
// Modules\Integration\Infrastructure\Sms\SmsManager in the container via Mockery's
// `driver->send` demeter chaining, which does not satisfy an interface
// type-hint (the resulting demeter mock doesn't implement it). Fixing that
// cleanly means changing how that test mocks SMS delivery, which is
// Integration-domain test surface out of scope for this session — deferred
// to Integration's own migration rather than forced through here.
use Modules\Integration\Infrastructure\Sms\SmsManager;
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
