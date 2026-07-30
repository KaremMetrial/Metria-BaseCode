<?php

declare(strict_types=1);

namespace Modules\Currency\Presentation\Policies;

// TODO: update when IAM module lands
use App\Domain\Auth\Models\User;
use Modules\Currency\Domain\Models\Currency;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyPolicy
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
        return $user->can('currencies.view');
    }

    public function view(User $user, ?Currency $currency = null): bool
    {
        return $user->can('currencies.view');
    }

    public function create(User $user): bool
    {
        return $user->can('currencies.manage');
    }

    public function update(User $user, ?Currency $currency = null): bool
    {
        return $user->can('currencies.manage');
    }

    public function delete(User $user, ?Currency $currency = null): bool
    {
        return $user->can('currencies.manage');
    }
}
