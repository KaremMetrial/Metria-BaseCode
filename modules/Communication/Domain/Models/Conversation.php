<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Communication\Domain\Enums\ConversationState;
use Modules\Communication\Domain\Enums\ConversationType;
use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasUuid;

/**
 * @property string $id
 * @property string $tenant_id
 * @property ConversationType $type
 * @property ConversationState $state
 * @property string|null $title
 * @property string $created_by
 * @property string|null $direct_key
 * @property int $next_sequence
 * @property int $version
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Conversation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'communication_conversations';

    protected $fillable = [
        'tenant_id',
        'type',
        'state',
        'title',
        'created_by',
        'direct_key',
        'next_sequence',
        'version',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'state' => ConversationState::class,
            'next_sequence' => 'integer',
            'version' => 'integer',
            'settings' => 'array',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SyncChange::class, 'conversation_id');
    }
}
