<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $endpoint_id
 * @property string $event
 * @property array $payload
 * @property string $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $response_body
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property WebhookEndpoint|null $endpoint
 */
class WebhookDelivery extends Model
{
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'endpoint_id',
        'event',
        'payload',
        'status',
        'attempts',
        'response_status',
        'response_body',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }
}
