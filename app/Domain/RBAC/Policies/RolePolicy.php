<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\RBAC\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('rbac.roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('rbac.roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('rbac.roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        if (! $user->hasPermissionTo('rbac.roles.manage')) {
            return false;
        }

        // Anti-Escalation: Cannot update a role with a higher priority (lower number) than the user's highest role
        $userHighestPriority = $this->getUserHighestPriority($user);
        $targetPriority = $role->metadata->priority ?? 100;

        return $userHighestPriority <= $targetPriority;
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->hasPermissionTo('rbac.roles.manage')) {
            return false;
        }

        $userHighestPriority = $this->getUserHighestPriority($user);
        $targetPriority = $role->metadata->priority ?? 100;

        return $userHighestPriority <= $targetPriority;
    }

    private function getUserHighestPriority(User $user): int
    {
        // Lower number = higher power. Default to 100 (lowest)
        return $user->roles()->with('metadata')->get()->min(function ($role) {
            return $role->metadata->priority ?? 100;
        }) ?? 100;
    }
}
