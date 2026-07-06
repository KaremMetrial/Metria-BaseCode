<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
