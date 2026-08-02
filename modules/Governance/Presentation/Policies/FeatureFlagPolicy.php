<?php

declare(strict_types=1);

namespace Modules\Governance\Presentation\Policies;

use Modules\Auth\Domain\Models\User;
use Modules\Governance\Domain\Models\FeatureFlag;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeatureFlagPolicy
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
        return $user->can('governance.flags.manage');
    }

    public function view(User $user, ?FeatureFlag $flag = null): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('governance.flags.manage');
    }

    public function update(User $user, ?FeatureFlag $flag = null): bool
    {
        return $user->can('governance.flags.manage');
    }

    public function delete(User $user, ?FeatureFlag $flag = null): bool
    {
        return $user->can('governance.flags.manage');
    }

    public function toggle(User $user, ?FeatureFlag $flag = null): bool
    {
        return $user->can('governance.flags.manage');
    }
}
