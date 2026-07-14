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

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($tenantId);
            }
            $cacheKey = 'spatie.permission.cache.'.($tenantId ?? 'system');
            config(['permission.cache.key' => $cacheKey]);

            if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
                $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
                if ($registrar->cacheKey !== $cacheKey) {
                    $registrar->cacheKey = $cacheKey;
                    $registrar->forgetCachedPermissions();
                }
            }
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

    /**
     * Run a callback within the scope of a given tenant ID, restoring the previous context afterwards.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function runInContext(int|string|null $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenantId);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }
}
