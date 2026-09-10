<?php

declare(strict_types=1);

namespace Modules\Integration\Presentation\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Integration\Domain\Models\OAuthProvider;
use Modules\Integration\Presentation\Http\Requests\UpdateOAuthProviderRequest;
use Modules\Integration\Presentation\Http\Resources\OAuthProviderResource;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;
use Modules\Shared\Presentation\Http\Controllers\BaseCrudController;

/**
 * index/show/update/destroy come from BaseCrudController; every lookup is
 * scoped to the caller's tenant (plus tenant_id=null "global" providers) at
 * the query level — findOrFail() throwing a plain 404 for another tenant's
 * row (rather than Gate::authorize() throwing 403) is what
 * OAuthProviderTenantIsolationTest asserts, so that scope has to live in
 * the query, not only in the Policy.
 *
 * store() stays custom: one OAuthProvider per (tenant, provider) is an
 * upsert, not a plain create.
 */
class OAuthProviderController extends BaseCrudController
{
    protected string $modelClass = OAuthProvider::class;

    protected string $resourceClass = OAuthProviderResource::class;

    protected string $storeRequestClass = UpdateOAuthProviderRequest::class;

    public function store(): JsonResponse
    {
        Gate::authorize('create', OAuthProvider::class);

        $request = app($this->storeRequestClass);
        $tenantId = app(TenantManager::class)->id();

        $provider = OAuthProvider::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $request->string('provider')->value(),
            ],
            $request->validated()
        );

        return $this->respondCreated(new OAuthProviderResource($provider));
    }

    protected function indexQuery(): Builder
    {
        return OAuthProvider::query()->forTenant(app(TenantManager::class)->id())->latest();
    }

    protected function findOrFail(string $id): Model
    {
        return OAuthProvider::query()->forTenant(app(TenantManager::class)->id())->findOrFail($id);
    }
}
