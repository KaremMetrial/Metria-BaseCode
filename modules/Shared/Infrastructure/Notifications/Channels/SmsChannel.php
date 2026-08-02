<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Notifications\Channels;

// Known residual coupling: Shared depends on the concrete Integration module
// here (Integration has since fully migrated into modules/, so this is no
// longer blocked by namespace migration — it's a deliberate, disclosed
// trade-off). An earlier attempt to depend on a Shared SmsProvider contract
// instead was reverted: tests/Feature/Auth/NotificationFlowTest.php mocks
// SmsManager in the container via Mockery's `driver->send` demeter chaining,
// which doesn't satisfy an interface type-hint (the resulting demeter mock
// doesn't implement it). Fixing that cleanly means changing how that test
// mocks SMS delivery — a real test-authoring change, not something to force
// as a side effect of a migration pass.
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
