<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\RBAC\Actions\SyncRolePermissionsAction;
use App\Domain\RBAC\Http\Resources\RoleResource;
use App\Domain\RBAC\Models\Role;
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

        $this->syncAction->execute($role, $validated['permissions'], 'add');

        return $this->respond(new RoleResource($role->load('permissions')), __('rbac.permissions_added'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('rbac.permissions.assign');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $this->syncAction->execute($role, $validated['permissions'], 'replace');

        return $this->respond(new RoleResource($role->load('permissions')), __('rbac.permissions_synced'));
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('rbac.permissions.assign');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $this->syncAction->execute($role, $validated['permissions'], 'remove');

        return $this->respondSuccess(__('rbac.permissions_removed'));
    }
}
