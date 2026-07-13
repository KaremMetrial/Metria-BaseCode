<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the X-Tenant header, or falls back to the
 * authenticated user's tenant_id. Attach as `tenant` middleware on routes.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tenancy.enabled')) {
            return $next($request);
        }

        $headerName = config('tenancy.header', 'X-Tenant-ID');
        $tenantId = $request->header($headerName)
            ?? $request->header('X-Tenant-ID')
            ?? $request->header('X-Tenant')
            ?? $request->user()?->tenant_id;

        $user = $request->user();
        
        if ($user && $tenantId !== null) {
            $userTenantId = $user->getAttributes()['tenant_id'] ?? null;
            if ($userTenantId !== null && (string) $userTenantId !== (string) $tenantId && ! $user->can('admin.super')) {
                $tenantId = $userTenantId;
            }
        }

        $this->manager->set($tenantId);

        if ($tenantId !== null && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($tenantId);
        }

        return $next($request);
    }
}
