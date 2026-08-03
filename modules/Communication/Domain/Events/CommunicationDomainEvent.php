<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;

/**
 * Version-one durable integration event. It is intentionally not mapped to the
 * realtime transport during Phase 2A.
 */
final class CommunicationDomainEvent extends DomainEvent implements StoredInOutbox
{
    /** @param array<string, mixed> $eventPayload */
    public function __construct(
        private readonly string $name,
        private readonly array $eventPayload,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return $this->name;
    }

    public function payload(): array
    {
        return $this->eventPayload;
    }
}
