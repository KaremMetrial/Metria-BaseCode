<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Actions;

use App\Core\Events\EventBus;
use App\Core\Exceptions\DomainException;
use App\Domain\RBAC\Contracts\PermissionRepositoryInterface;
use App\Domain\RBAC\Events\RolePermissionsUpdated;
use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Facades\DB;

class SyncRolePermissionsAction
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissionRepository,
        private readonly EventBus $eventBus
    ) {}

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function execute(Role $role, array $permissionNames, string $mode = 'replace'): Role
    {
        if ($role->metadata && ! $role->metadata->is_editable) {
            throw new DomainException(__('rbac.role_not_editable', ['role' => $role->name]), 'role_not_editable');
        }

        // Validate that permissions exist
        $permissions = $this->permissionRepository->findByNames($permissionNames);
        if ($permissions->count() !== count($permissionNames)) {
            throw new DomainException(__('rbac.invalid_permissions_provided'), 'invalid_permissions_provided');
        }

        return DB::transaction(function () use ($role, $permissionNames, $mode) {
            if ($mode === 'replace') {
                $role->syncPermissions($permissionNames);
            } elseif ($mode === 'add') {
                $role->givePermissionTo($permissionNames);
            } elseif ($mode === 'remove') {
                foreach ($permissionNames as $permissionName) {
                    $role->revokePermissionTo($permissionName);
                }
            }

            // Pluck the exact final permissions for the event payload
            $finalPermissions = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $role->permissions()->pluck('name')->toArray());

            $this->eventBus->publish(new RolePermissionsUpdated($role, $finalPermissions));

            return $role;
        });
    }
}
