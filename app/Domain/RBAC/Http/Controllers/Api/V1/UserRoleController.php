<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Auth\Models\User;
use App\Domain\RBAC\Actions\SyncUserRolesAction;
use App\Domain\RBAC\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Core\Tenancy\TenantManager;

class UserRoleController extends ApiController
{
    public function __construct(private readonly SyncUserRolesAction $syncAction) {}

    public function index(User $user): JsonResponse
    {
        $this->authorize('rbac.roles.view');

        return $this->respond(RoleResource::collection($user->roles));
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $this->authorize('rbac.roles.manage');

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where(function ($query) {
                $query->where('tenant_id', app(TenantManager::class)->id())->orWhereNull('tenant_id');
            })],
        ]);

        $this->syncAction->execute($user, $validated['roles'], 'add');

        return $this->respond(RoleResource::collection($user->refresh()->roles), __('rbac.roles_added'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('rbac.roles.manage');

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where(function ($query) {
                $query->where('tenant_id', app(TenantManager::class)->id())->orWhereNull('tenant_id');
            })],
        ]);

        $this->syncAction->execute($user, $validated['roles'], 'replace');

        return $this->respond(RoleResource::collection($user->refresh()->roles), __('rbac.roles_synced'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('rbac.roles.manage');

        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where(function ($query) {
                $query->where('tenant_id', app(TenantManager::class)->id())->orWhereNull('tenant_id');
            })],
        ]);

        $this->syncAction->execute($user, $validated['roles'], 'remove');

        return $this->respond(null, __('rbac.roles_removed'));
    }
}
