<?php

declare(strict_types=1);

namespace Modules\RBAC\Domain\Models;

use Modules\RBAC\Infrastructure\Scopes\SystemAwareTenantScope;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property int|string $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $tenant_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Modules\RBAC\Domain\Models\RoleMetadata|null $metadata
 */
class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'tenant_id'];

    protected static function boot()
    {
        parent::boot();

        // Enforce tenant isolation while preserving global system roles
        static::addGlobalScope(new SystemAwareTenantScope);
    }

    protected static function booted()
    {
        static::saved(function ($role) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        static::deleted(function ($role) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(RoleMetadata::class, 'role_id');
    }
}
