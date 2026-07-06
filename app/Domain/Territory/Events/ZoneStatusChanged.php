<?php

declare(strict_types=1);

namespace App\Domain\Territory\Events;

use App\Core\Events\BroadcastableEvent;
use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Territory\Models\Zone;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ZoneStatusChanged extends DomainEvent implements ShouldBroadcastNow, StoredInOutbox
{
    use BroadcastableEvent;

    public function __construct(public readonly Zone $zone, public readonly string $statusReason = 'updated')
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'zone.status_changed';
    }

    public function payload(): array
    {
        return [
            'zone_id' => $this->zone->id,
            'tenant_id' => $this->zone->tenant_id,
            'city_id' => $this->zone->city_id,
            'code' => $this->zone->code,
            'is_active' => $this->zone->is_active,
            'reason' => $this->statusReason,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('territories.zone.'.$this->zone->id),
        ];
    }
}
