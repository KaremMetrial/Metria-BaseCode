<?php

declare(strict_types=1);

namespace App\Domain\Territory\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Territory\Models\Zone;
use Illuminate\Auth\Access\HandlesAuthorization;

class ZonePolicy
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
        return $user->can('zones.view');
    }

    public function view(User $user, ?Zone $zone = null): bool
    {
        return $user->can('zones.view');
    }

    public function create(User $user): bool
    {
        return $user->can('zones.manage');
    }

    public function update(User $user, ?Zone $zone = null): bool
    {
        return $user->can('zones.manage');
    }

    public function delete(User $user, ?Zone $zone = null): bool
    {
        return $user->can('zones.manage');
    }

    public function track(User $user, ?Zone $zone = null): bool
    {
        return $user->can('couriers.track') || $user->can('zones.view');
    }
}
