<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Actions;

use Modules\Shared\Infrastructure\Events\EventBus;
use Modules\Shared\Application\Exceptions\DomainException;
use Modules\RBAC\Domain\Contracts\RoleRepositoryInterface;
use Modules\RBAC\Domain\Events\RoleDeleted;
use Modules\RBAC\Domain\Models\Role;
use Illuminate\Support\Facades\DB;

class DeleteRoleAction
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly EventBus $eventBus
    ) {}

    public function execute(Role $role): bool
    {
        if ($role->metadata && $role->metadata->is_system) {
            throw new DomainException(__('rbac.cannot_delete_system_role', ['role' => $role->name]), 'cannot_delete_system_role');
        }

        if ($role->users()->count() > 0) {
            throw new DomainException(__('rbac.cannot_delete_role_in_use', ['role' => $role->name]), 'cannot_delete_role_in_use');
        }

        return DB::transaction(function () use ($role) {
            $roleId = $role->id;
            $roleName = $role->name;

            $deleted = $this->roleRepository->delete($role);

            if ($deleted) {
                $this->eventBus->publish(new RoleDeleted($roleId, $roleName));
            }

            return $deleted;
        });
    }
}
