<?php

declare(strict_types=1);

namespace App\Domain\Integration\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OAuthProvider extends Model
{
    use HasUuid;

    protected $table = 'oauth_providers';

    protected $fillable = [
        'tenant_id',
        'provider',
        'client_id',
        'client_secret',
        'redirect_url',
        'scopes',
        'is_enabled',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'scopes' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }
}
