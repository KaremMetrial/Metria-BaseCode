<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Actions;

use Modules\Shared\Infrastructure\Events\EventBus;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;
use Modules\RBAC\Domain\Contracts\RoleRepositoryInterface;
use Modules\RBAC\Application\DTOs\CreateRoleDTO;
use Modules\RBAC\Domain\Events\RoleCreated;
use Modules\RBAC\Domain\Models\Role;
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

            $tenantId = app(TenantManager::class)->id();
            $role = $this->roleRepository->createWithMetadata($roleData, $metadata, (string) $tenantId);

            $this->eventBus->publish(new RoleCreated($role));

            return $role;
        });
    }
}
