<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\RBAC\Actions\CreateRoleAction;
use App\Domain\RBAC\Actions\DeleteRoleAction;
use App\Domain\RBAC\Actions\UpdateRoleAction;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\Http\Requests\StoreRoleRequest;
use App\Domain\RBAC\Http\Requests\UpdateRoleRequest;
use App\Domain\RBAC\Http\Resources\RoleResource;
use App\Domain\RBAC\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly CreateRoleAction $createAction,
        private readonly UpdateRoleAction $updateAction,
        private readonly DeleteRoleAction $deleteAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = $this->roleRepository->all();

        return $this->respond(RoleResource::collection($roles));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);
        $dto = \App\Domain\RBAC\DTOs\CreateRoleDTO::fromArray($request->validated());
        $role = $this->createAction->execute($dto, (string) $request->user()?->id);

        return $this->respond(new RoleResource($role), __('rbac.role_created'), 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return $this->respond(new RoleResource($role->load(['metadata', 'permissions'])));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);
        $dto = \App\Domain\RBAC\DTOs\UpdateRoleDTO::fromArray($request->validated());
        $updatedRole = $this->updateAction->execute($role, $dto, (string) $request->user()?->id);

        return $this->respond(new RoleResource($updatedRole), __('rbac.role_updated'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $this->deleteAction->execute($role);

        return $this->respond(null, __('rbac.role_deleted'));
    }
}
