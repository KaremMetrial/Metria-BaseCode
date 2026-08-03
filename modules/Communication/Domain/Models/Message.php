<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Communication\Domain\Enums\MessageKind;
use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasUuid;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $conversation_id
 * @property string $author_actor_id
 * @property int $sequence
 * @property MessageKind $kind
 * @property array<string, mixed> $content
 * @property string $state
 * @property int $revision
 * @property string|null $client_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Message extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'communication_messages';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'author_actor_id',
        'sequence',
        'kind',
        'content',
        'state',
        'revision',
        'client_message_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
            'content' => 'array',
            'sequence' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
