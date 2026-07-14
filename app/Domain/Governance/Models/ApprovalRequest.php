<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $action
 * @property array $payload
 * @property ApprovalStatus $status
 * @property string|null $reason
 * @property string $requested_by
 * @property string|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User|null $requester
 * @property User|null $approver
 */
class ApprovalRequest extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'tenant_id', 'action', 'payload', 'status', 'reason',
        'requested_by', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
