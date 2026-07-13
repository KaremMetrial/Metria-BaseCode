<?php

declare(strict_types=1);

namespace App\Domain\Integration\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Core\Tenancy\TenantManager;
use App\Domain\Integration\Http\Requests\UpdateOAuthProviderRequest;
use App\Domain\Integration\Models\OAuthProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OAuthProviderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', OAuthProvider::class);
        $tenantId = app(TenantManager::class)->id() ?: ($request->header('X-Tenant-ID') ?: null);
        $providers = OAuthProvider::query()->forTenant($tenantId)->get();

        return $this->respond(['providers' => $providers]);
    }

    public function store(UpdateOAuthProviderRequest $request): JsonResponse
    {
        Gate::authorize('create', OAuthProvider::class);
        $tenantId = app(TenantManager::class)->id() ?: ($request->header('X-Tenant-ID') ?: null);

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
        $provider = OAuthProvider::query()->findOrFail($id);
        Gate::authorize('view', $provider);

        return $this->respond(['provider' => $provider]);
    }

    public function update(UpdateOAuthProviderRequest $request, string $id): JsonResponse
    {
        $provider = OAuthProvider::query()->findOrFail($id);
        Gate::authorize('update', $provider);
        $provider->update($request->validated());

        return $this->respond(['provider' => $provider]);
    }

    public function destroy(string $id): JsonResponse
    {
        $provider = OAuthProvider::query()->findOrFail($id);
        Gate::authorize('delete', $provider);
        $provider->delete();

        return $this->respondNoContent();
    }
}
