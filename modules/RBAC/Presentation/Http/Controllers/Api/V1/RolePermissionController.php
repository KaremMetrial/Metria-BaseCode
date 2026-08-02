<?php

declare(strict_types=1);

namespace Modules\RBAC\Presentation\Http\Controllers\Api\V1;

use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\RBAC\Infrastructure\Actions\SyncRolePermissionsAction;
use Modules\RBAC\Presentation\Http\Resources\RoleResource;
use Modules\RBAC\Domain\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends ApiController
{
    public function __construct(private readonly SyncRolePermissionsAction $syncAction) {}

    public function index(Role $role): JsonResponse
    {
        $this->authorize('rbac.permissions.view');

        return $this->respond($role->permissions()->pluck('name'));
    }

    public function store(Request $request, Role $role): JsonResponse
    {
        $this->authorize('rbac.permissions.assign');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);
        $validatedArray = is_array($validated) ? $validated : [];
        $permissionsVal = $validatedArray['permissions'] ?? [];
        /** @var array<int, string> $permissions */
        $permissions = is_array($permissionsVal) ? array_filter($permissionsVal, 'is_string') : [];

        $this->syncAction->execute($role, $permissions, 'add');

        return $this->respond(new RoleResource($role->load('permissions')), __('rbac.permissions_added'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('rbac.permissions.assign');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);
        $validatedArray = is_array($validated) ? $validated : [];
        $permissionsVal = $validatedArray['permissions'] ?? [];
        /** @var array<int, string> $permissions */
        $permissions = is_array($permissionsVal) ? array_filter($permissionsVal, 'is_string') : [];

        $this->syncAction->execute($role, $permissions, 'replace');

        return $this->respond(new RoleResource($role->load('permissions')), __('rbac.permissions_synced'));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('rbac.permissions.assign');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);
        $validatedArray = is_array($validated) ? $validated : [];
        $permissionsVal = $validatedArray['permissions'] ?? [];
        /** @var array<int, string> $permissions */
        $permissions = is_array($permissionsVal) ? array_filter($permissionsVal, 'is_string') : [];

        $this->syncAction->execute($role, $permissions, 'remove');

        return $this->respond(null, __('rbac.permissions_removed'));
    }
}
