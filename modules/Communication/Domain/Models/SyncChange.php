<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasUuid;

/**
 * Durable, per-conversation change ledger. It makes REST cursor sync the
 * recovery authority without depending on Socket.IO history.
 */
/**
 * @property string $id
 * @property string $tenant_id
 * @property string $conversation_id
 * @property int $change_version
 * @property string $change_type
 * @property string|null $message_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SyncChange extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'communication_sync_changes';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'change_version',
        'change_type',
        'message_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'change_version' => 'integer',
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
