<?php

declare(strict_types=1);

namespace App\Core\Outbox;

use App\Core\Events\DomainEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Transactional outbox row. Written inside the same DB transaction as the
 * state change; published asynchronously so no event is lost and no event
 * refers to rolled-back state.
 *
 * @property string $id
 * @property string $event_name
 * @property string $event_class
 * @property array $payload
 * @property \Carbon\CarbonImmutable|null $occurred_at
 * @property \Carbon\CarbonImmutable|null $published_at
 * @property int $attempts
 * @property string|null $last_error
 * @property \Carbon\CarbonImmutable|null $reserved_at
 * @property string|null $reserved_by
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 */
class OutboxMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'event_name', 'event_class', 'payload', 'occurred_at',
        'published_at', 'attempts', 'last_error', 'reserved_at', 'reserved_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'reserved_at' => 'immutable_datetime',
        ];
    }

    public static function record(DomainEvent $event): self
    {
        return self::query()->create([
            'id' => $event->eventId,
            'event_name' => $event->eventName(),
            'event_class' => $event::class,
            'payload' => $event->payload(),
            'occurred_at' => $event->occurredAt,
        ]);
    }
}
