<?php

declare(strict_types=1);

namespace App\Domain\Auth\Listeners;

use App\Domain\Governance\Services\AuditLogger;

class AuditSecurityEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(object $event): void
    {
        $eventName = method_exists($event, 'eventName') ? $event->eventName() : get_class($event);
        $payload = method_exists($event, 'payload') ? $event->payload() : [];

        $user = $event->user ?? null;

        $this->audit->log("security.{$eventName}", $user, $payload);
    }
}
