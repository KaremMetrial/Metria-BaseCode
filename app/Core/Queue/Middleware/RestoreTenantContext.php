<?php

declare(strict_types=1);

namespace App\Core\Queue\Middleware;

use App\Core\Tenancy\TenantManager;
use Closure;
use Spatie\Permission\PermissionRegistrar;

class RestoreTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        $tenantIdVal = null;

        if (method_exists($job, 'payload')) {
            $payload = $job->payload();
            if (is_array($payload)) {
                $tenantIdVal = $payload['tenant_id'] ?? null;
            }
        }

        if ($tenantIdVal === null && property_exists($job, 'tenantId')) {
            $tenantIdVal = $job->tenantId;
        }

        if ($tenantIdVal === null && property_exists($job, 'tenant_id')) {
            $tenantIdVal = $job->tenant_id;
        }

        $tenantId = (is_string($tenantIdVal) || is_int($tenantIdVal)) ? $tenantIdVal : null;

        $manager = app(TenantManager::class);
        $previousTenantId = $manager->id();

        $manager->set($tenantId);

        if (class_exists(PermissionRegistrar::class)) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($tenantId);
            }
            $cacheKey = 'spatie.permission.cache.'.($tenantId ?? 'system');
            config(['permission.cache.key' => $cacheKey]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->cacheKey = $cacheKey;
            $registrar->forgetCachedPermissions();
        }

        try {
            $next($job);
        } finally {
            $manager->set($previousTenantId);

            if (class_exists(PermissionRegistrar::class)) {
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId($previousTenantId);
                }
                $cacheKey = 'spatie.permission.cache.'.($previousTenantId ?? 'system');
                config(['permission.cache.key' => $cacheKey]);

                $registrar = app(PermissionRegistrar::class);
                $registrar->cacheKey = $cacheKey;
                $registrar->forgetCachedPermissions();
            }
        }
    }
}
