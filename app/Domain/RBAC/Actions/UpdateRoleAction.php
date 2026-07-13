<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Actions;

use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\DTOs\UpdateRoleDTO;
use App\Domain\RBAC\Exceptions\RoleNotEditableException;
use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Facades\DB;

class UpdateRoleAction
{
    public function __construct(private readonly RoleRepositoryInterface $roleRepository) {}

    public function execute(Role $role, UpdateRoleDTO $dto, ?string $userId = null): Role
    {
        if ($role->metadata && ! $role->metadata->is_editable) {
            throw new RoleNotEditableException($role->name);
        }

        return DB::transaction(function () use ($role, $dto, $userId) {
            $roleData = [];
            if ($dto->name !== null) {
                $roleData['name'] = $dto->name;
            }
            if ($dto->guardName !== null) {
                $roleData['guard_name'] = $dto->guardName;
            }

            $metadata = [];
            
            if ($dto->displayName !== null) $metadata['display_name'] = $dto->displayName;
            if ($dto->description !== null) $metadata['description'] = $dto->description;
            if ($dto->priority !== null) $metadata['priority'] = $dto->priority;
            if ($dto->isSystem !== null) $metadata['is_system'] = $dto->isSystem;
            if ($dto->isEditable !== null) $metadata['is_editable'] = $dto->isEditable;
            if ($dto->isAssignable !== null) $metadata['is_assignable'] = $dto->isAssignable;

            if ($userId) {
                $metadata['updated_by'] = $userId;
            }

            return $this->roleRepository->updateWithMetadata($role, $roleData, $metadata);
        });
    }
}
