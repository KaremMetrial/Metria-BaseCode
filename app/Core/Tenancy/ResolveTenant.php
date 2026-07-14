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

        $user = $request->user();
        $userTenantId = $user ? ($user->getAttributes()['tenant_id'] ?? null) : null;

        $headerName = config('tenancy.header', 'X-Tenant-ID');
        $tenantId = $request->header($headerName)
            ?? $request->header('X-Tenant-ID')
            ?? $request->header('X-Tenant')
            ?? $userTenantId;

        if ($user && $tenantId !== null) {
            if ($userTenantId !== null && (string) $userTenantId !== (string) $tenantId && ! $user->can('admin.super')) {
                $tenantId = $userTenantId;
            }
        }

        $this->manager->set($tenantId);

        return $next($request);
    }
}
