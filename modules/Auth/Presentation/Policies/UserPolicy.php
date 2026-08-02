<?php

declare(strict_types=1);

namespace Modules\Auth\Presentation\Policies;

use Modules\Auth\Domain\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admin override: grant all abilities if user has admin.super permission.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('admin.super')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, ?User $model = null): bool
    {
        return ($model !== null && (string) $user->id === (string) $model->id) || $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, ?User $model = null): bool
    {
        return ($model !== null && (string) $user->id === (string) $model->id) || $user->can('users.update');
    }

    public function delete(User $user, ?User $model = null): bool
    {
        return $user->can('users.delete');
    }
}
