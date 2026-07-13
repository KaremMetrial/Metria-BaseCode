<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Actions;

use App\Core\Events\EventBus;
use App\Core\Exceptions\DomainException;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\Events\RoleDeleted;
use App\Domain\RBAC\Models\Role;
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
