<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Modules\Shared\Infrastructure\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $provider
 * @property string $client_id
 * @property string $client_secret
 * @property string $redirect_url
 * @property array<int, string>|null $scopes
 * @property bool $is_enabled
 */
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
