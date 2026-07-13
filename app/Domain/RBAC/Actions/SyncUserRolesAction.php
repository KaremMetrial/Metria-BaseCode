<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Actions;

use App\Core\Events\EventBus;
use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Models\User;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\Events\UserRolesUpdated;
use Illuminate\Support\Facades\DB;

class SyncUserRolesAction
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly EventBus $eventBus
    ) {}

    /**
     * @param  array<int, string>  $roleNames
     */
    public function execute(User $user, array $roleNames, string $mode = 'replace'): User
    {
        // Validate roles exist and are assignable
        $roles = $this->roleRepository->all()->whereIn('name', $roleNames);

        if ($roles->count() !== count($roleNames)) {
            throw new DomainException(__('rbac.invalid_roles_provided'), 'invalid_roles_provided');
        }

        foreach ($roles as $role) {
            if ($role->metadata && ! $role->metadata->is_assignable) {
                throw new DomainException(__('rbac.role_not_assignable', ['role' => $role->name]), 'role_not_assignable');
            }
        }

        return DB::transaction(function () use ($user, $roleNames, $mode) {
            if ($mode === 'replace') {
                $user->syncRoles($roleNames);
            } elseif ($mode === 'add') {
                $user->assignRole($roleNames);
            } elseif ($mode === 'remove') {
                foreach ($roleNames as $roleName) {
                    $user->removeRole($roleName);
                }
            }

            $finalRoles = $user->roles()->pluck('name')->toArray();

            $this->eventBus->publish(new UserRolesUpdated($user, $finalRoles));

            return $user;
        });
    }
}
