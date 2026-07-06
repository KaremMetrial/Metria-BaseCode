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

        $tenantId = $request->header(config('tenancy.header', 'X-Tenant'))
            ?? $request->user()?->tenant_id;

        $this->manager->set($tenantId);

        return $next($request);
    }
}
