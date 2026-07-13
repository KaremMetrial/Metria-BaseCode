<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Actions;

use App\Core\Events\EventBus;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\DTOs\CreateRoleDTO;
use App\Domain\RBAC\Events\RoleCreated;
use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Facades\DB;

class CreateRoleAction
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly EventBus $eventBus
    ) {}

    public function execute(CreateRoleDTO $dto, ?string $userId = null): Role
    {
        return DB::transaction(function () use ($dto, $userId) {
            $roleData = [
                'name' => $dto->name,
                'guard_name' => $dto->guardName ?? 'web',
            ];

            $metadata = [
                'display_name' => $dto->displayName,
                'description' => $dto->description,
                'priority' => $dto->priority,
                'is_system' => $dto->isSystem,
                'is_editable' => $dto->isEditable,
                'is_assignable' => $dto->isAssignable,
                'created_by' => $userId,
            ];

            $tenantId = app(\App\Core\Tenancy\TenantManager::class)->id();
            $role = $this->roleRepository->createWithMetadata($roleData, $metadata, (string) $tenantId);

            $this->eventBus->publish(new RoleCreated($role));

            return $role;
        });
    }
}
