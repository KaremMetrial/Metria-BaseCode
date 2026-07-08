<?php

declare(strict_types=1);

namespace App\Domain\Territory\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Territory\Models\Country;
use Illuminate\Auth\Access\HandlesAuthorization;

class CountryPolicy
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
        return $user->can('territories.view');
    }

    public function view(User $user, ?Country $country = null): bool
    {
        return $user->can('territories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('territories.manage');
    }

    public function update(User $user, ?Country $country = null): bool
    {
        return $user->can('territories.manage');
    }

    public function delete(User $user, ?Country $country = null): bool
    {
        return $user->can('territories.manage');
    }
}
