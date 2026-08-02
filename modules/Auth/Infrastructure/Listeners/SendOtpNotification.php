<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Notifications\OtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

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

        if (str_contains($identifier, '@')) {
            Notification::route('mail', $identifier)
                ->notify(new OtpNotification($event->code));
        } else {
            Notification::route('sms', $identifier)
                ->notify(new OtpNotification($event->code));
        }
    }
}
