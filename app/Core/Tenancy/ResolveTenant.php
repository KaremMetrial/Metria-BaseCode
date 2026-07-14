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
            $response = $next($request);
            if ($response instanceof Response) {
                return $response;
            }
            throw new \UnexpectedValueException('Expected Response instance.');
        }

        $user = $request->user();
        $userTenantId = null;
        if ($user !== null) {
            $attr = $user->getAttributes();
            if (isset($attr['tenant_id']) && is_scalar($attr['tenant_id'])) {
                $userTenantId = (string) $attr['tenant_id'];
            }
        }

        $headerNameVal = config('tenancy.header', 'X-Tenant-ID');
        $headerName = is_string($headerNameVal) ? $headerNameVal : 'X-Tenant-ID';
        $h1 = $request->header($headerName);
        $headerVal = is_array($h1) ? reset($h1) : $h1;

        $h2 = $request->header('X-Tenant-ID');
        $h2Val = is_array($h2) ? reset($h2) : $h2;

        $h3 = $request->header('X-Tenant');
        $h3Val = is_array($h3) ? reset($h3) : $h3;

        $tenantIdRaw = ($headerVal !== '' && $headerVal !== null) ? $headerVal : (($h2Val !== '' && $h2Val !== null) ? $h2Val : (($h3Val !== '' && $h3Val !== null) ? $h3Val : $userTenantId));
        $tenantId = is_scalar($tenantIdRaw) ? (string) $tenantIdRaw : null;

        if ($user && $tenantId !== null) {
            if ($userTenantId !== null && $userTenantId !== $tenantId && ! $user->can('admin.super')) {
                $tenantId = $userTenantId;
            }
        }

        $this->manager->set($tenantId);

        $response = $next($request);
        if ($response instanceof Response) {
            return $response;
        }
        throw new \UnexpectedValueException('Expected Response instance.');
    }
}
