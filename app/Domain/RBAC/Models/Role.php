<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Models;

use App\Domain\RBAC\Scopes\SystemAwareTenantScope;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

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
