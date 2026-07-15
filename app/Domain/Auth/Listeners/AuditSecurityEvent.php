<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Governance\Services\AuditLogger;

class AuditSecurityEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(object $event): void
    {
        $eventNameVal = method_exists($event, 'eventName') ? $event->eventName() : get_class($event);
        $eventName = is_scalar($eventNameVal) ? (string) $eventNameVal : get_class($event);
        $payloadVal = method_exists($event, 'payload') ? $event->payload() : [];
        $payload = is_array($payloadVal) ? $payloadVal : [];

        $userVal = property_exists($event, 'user') ? $event->user : null;
        $user = $userVal instanceof \Illuminate\Database\Eloquent\Model ? $userVal : null;

        $this->audit->log("security.{$eventName}", $user, $payload);
    }
}
