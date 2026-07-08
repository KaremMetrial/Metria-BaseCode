<?php

declare(strict_types=1);

namespace App\Domain\Auth\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSocialIdentity;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserSocialIdentityPolicy
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

    public function view(User $user, ?UserSocialIdentity $identity = null): bool
    {
        return ($identity !== null && (string) $user->id === (string) $identity->user_id) || $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, ?UserSocialIdentity $identity = null): bool
    {
        return ($identity !== null && (string) $user->id === (string) $identity->user_id) || $user->can('users.update');
    }
}
