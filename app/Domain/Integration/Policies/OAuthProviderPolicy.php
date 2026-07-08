<?php

declare(strict_types=1);

namespace App\Domain\Integration\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Integration\Models\OAuthProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class OAuthProviderPolicy
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
        return $user->can('integrations.oauth.view');
    }

    public function view(User $user, ?OAuthProvider $provider = null): bool
    {
        return $user->can('integrations.oauth.view');
    }

    public function create(User $user): bool
    {
        return $user->can('integrations.oauth.manage');
    }

    public function update(User $user, ?OAuthProvider $provider = null): bool
    {
        return $user->can('integrations.oauth.manage');
    }

    public function delete(User $user, ?OAuthProvider $provider = null): bool
    {
        return $user->can('integrations.oauth.manage');
    }

    public function toggle(User $user, ?OAuthProvider $provider = null): bool
    {
        return $user->can('integrations.oauth.manage');
    }
}
