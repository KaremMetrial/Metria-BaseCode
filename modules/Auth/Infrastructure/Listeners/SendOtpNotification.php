<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Domain\Events\OtpGenerated;
use Modules\Auth\Infrastructure\Notifications\OtpNotification;

class SendOtpNotification implements ShouldQueue
{
    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 15;

    public bool $failOnTimeout = true;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function handle(OtpGenerated $event): void
    {
        $identifier = $event->identifier;

        // An ad-hoc route (no User model attached, e.g. a registration OTP
        // sent before the account exists) has no locale of its own — this
        // listener runs on the queue with no request context, so the
        // locale has to travel on the event itself (set at dispatch time,
        // inside the original request).
        if (str_contains($identifier, '@')) {
            Notification::route('mail', $identifier)
                ->notify((new OtpNotification($event->code))->locale($event->locale));
        } else {
            Notification::route('sms', $identifier)
                ->notify((new OtpNotification($event->code))->locale($event->locale));
        }
    }
}
