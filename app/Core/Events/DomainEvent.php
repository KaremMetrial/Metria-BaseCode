<?php

declare(strict_types=1);

namespace App\Core\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Base class for all domain events. Every event carries its own identity and
 * timestamp so it can be stored, replayed, or forwarded to external systems.
 */
abstract class DomainEvent
{
    use Dispatchable;
    use SerializesModels;

    public readonly string $eventId;

    public readonly CarbonImmutable $occurredAt;

    public function __construct()
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = CarbonImmutable::now();
    }

    /**
     * Dot-notation event name used for outgoing webhooks and logs,
     * e.g. "payment.succeeded".
     */
    abstract public function eventName(): string;

    /**
     * Serializable payload describing what happened.
     */
    abstract public function payload(): array;
}
