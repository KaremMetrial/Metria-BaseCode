<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'context',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
