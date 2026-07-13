<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use Spatie\Permission\PermissionRegistrar;

/**
 * Holds the tenant for the current request/job. Registered as a singleton.
 */
class TenantManager
{
    private int|string|null $tenantId = null;

    public function set(int|string|null $tenantId): void
    {
        $this->tenantId = $tenantId;

        // Securely partition Spatie's cache to prevent multi-tenant cache bleeding
        if (class_exists(PermissionRegistrar::class)) {
            // Set Spatie's team context to our tenant ID
            setPermissionsTeamId($tenantId);

            $cacheKey = 'spatie.permission.cache.'.($tenantId ?? 'system');
            config(['permission.cache.key' => $cacheKey]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->cacheKey = $cacheKey;
            $registrar->forgetCachedPermissions(); // Clear in-memory cache for the new context
        }
    }

    public function id(): int|string|null
    {
        return $this->tenantId;
    }

    public function check(): bool
    {
        return $this->tenantId !== null;
    }
}
