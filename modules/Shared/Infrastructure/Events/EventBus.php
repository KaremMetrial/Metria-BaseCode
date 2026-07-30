<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Events;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Modules\Shared\Infrastructure\Persistence\Models\OutboxMessage;

/**
 * Single entry point for publishing domain events.
 *
 *  - In-process listeners fire through Laravel's dispatcher (after commit
 *    when inside a transaction, via DB::afterCommit()).
 *  - Events marked StoredInOutbox are ALSO written to the outbox table in the
 *    same transaction (transactional outbox pattern), then relayed to
 *    external consumers (webhooks, queues, other services) by the
 *    outbox:publish command.
 */
class EventBus
{
    public function publish(DomainEvent $event): void
    {
        if ($event instanceof StoredInOutbox) {
            OutboxMessage::record($event);
        }

        // Deliver to in-process listeners only once the transaction commits,
        // or immediately during unit tests when RefreshDatabase wraps in transaction.
        if (app()->runningUnitTests()) {
            event($event);
        } else {
            DB::afterCommit(
                fn () => event($event),
            );
        }
    }
}
