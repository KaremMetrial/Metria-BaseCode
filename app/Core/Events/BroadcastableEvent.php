<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Trait for domain events that should be broadcast over real-time WebSockets
 * (Socket.IO or Reverb/Pusher) immediately upon transaction commit.
 *
 * Classes using this trait MUST implement ShouldBroadcastNow or ShouldBroadcast
 * and define broadcastOn().
 */
trait BroadcastableEvent
{
    /**
     * Get the name the event should be broadcast as.
     */
    public function broadcastAs(): string
    {
        return $this->eventName();
    }

    /**
     * Get the data to broadcast.
     * Enriches the domain payload with standardized enterprise metadata.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->payload(), [
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ]);
    }
}
