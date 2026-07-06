<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Outbox\OutboxMessage;
use Illuminate\Support\Facades\DB;

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

        // Deliver to in-process listeners only once the transaction commits.
        DB::afterCommit(
            fn () => event($event),
        );
    }
}
