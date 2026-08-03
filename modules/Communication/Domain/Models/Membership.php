<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Communication\Domain\Enums\MembershipRole;
use Modules\Communication\Domain\Enums\MembershipState;
use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasUuid;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $conversation_id
 * @property string $actor_id
 * @property MembershipRole $role
 * @property MembershipState $state
 * @property int $last_read_sequence
 * @property int $last_delivered_sequence
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Membership extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'communication_memberships';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'actor_id',
        'role',
        'state',
        'last_read_sequence',
        'last_delivered_sequence',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'state' => MembershipState::class,
            'last_read_sequence' => 'integer',
            'last_delivered_sequence' => 'integer',
            'version' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
