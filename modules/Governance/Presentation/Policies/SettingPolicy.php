<?php

declare(strict_types=1);

namespace App\Domain\Governance\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Governance\Models\Setting;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
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
        return $user->can('governance.settings.view');
    }

    public function view(User $user, ?Setting $setting = null): bool
    {
        return $user->can('governance.settings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('governance.settings.manage');
    }

    public function update(User $user, ?Setting $setting = null): bool
    {
        return $user->can('governance.settings.manage');
    }

    public function delete(User $user, ?Setting $setting = null): bool
    {
        return $user->can('governance.settings.manage');
    }
}
