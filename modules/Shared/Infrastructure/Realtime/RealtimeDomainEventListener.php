<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Shared\Domain\Events\DomainEvent;

/** Queue retries make internal Redis publication at-least-once. */
final class RealtimeDomainEventListener implements ShouldQueue
{
    public function __construct(
        private readonly RealtimeEventMapper $mapper,
        private readonly RealtimePublisherContract $publisher,
    ) {}

    public string $queue = 'realtime';

    public int $tries = 5;

    public int $timeout = 15;

    public bool $afterCommit = true;

    public function backoff(): array
    {
        return [1, 5, 15, 30];
    }

    public function handle(DomainEvent $event): void
    {
        $envelope = $this->mapper->map($event);
        if ($envelope !== null) {
            $this->publisher->publish($envelope);
        }
    }
}
