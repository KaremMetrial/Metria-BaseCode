<?php

declare(strict_types=1);

namespace Modules\Integration\Presentation\Http\Controllers\Api\V1;

use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;
use Modules\Integration\Presentation\Http\Requests\UpdateOAuthProviderRequest;
use Modules\Integration\Domain\Models\OAuthProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OAuthProviderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', OAuthProvider::class);
        // Tenant scope only ever comes from the resolved request context —
        // never a client-supplied header, which would let a caller with a
        // null tenant context list another tenant's providers by header alone.
        $tenantId = app(TenantManager::class)->id();
        $providers = OAuthProvider::query()->forTenant($tenantId)->get();

        return $this->respond(['providers' => $providers]);
    }

    public function store(UpdateOAuthProviderRequest $request): JsonResponse
    {
        Gate::authorize('create', OAuthProvider::class);
        $tenantId = app(TenantManager::class)->id();

        $provider = OAuthProvider::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $request->string('provider')->value(),
            ],
            $request->validated()
        );

        return $this->respondCreated(['provider' => $provider]);
    }

    public function show(string $id): JsonResponse
    {
        $provider = OAuthProvider::query()->forTenant(app(TenantManager::class)->id())->findOrFail($id);
        Gate::authorize('view', $provider);

        return $this->respond(['provider' => $provider]);
    }

    public function update(UpdateOAuthProviderRequest $request, string $id): JsonResponse
    {
        $provider = OAuthProvider::query()->forTenant(app(TenantManager::class)->id())->findOrFail($id);
        Gate::authorize('update', $provider);
        $provider->update($request->validated());

        return $this->respond(['provider' => $provider]);
    }

    public function destroy(string $id): JsonResponse
    {
        $provider = OAuthProvider::query()->forTenant(app(TenantManager::class)->id())->findOrFail($id);
        Gate::authorize('delete', $provider);
        $provider->delete();

        return $this->respondNoContent();
    }
}
