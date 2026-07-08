<?php

declare(strict_types=1);

namespace App\Domain\Currency\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Currency\Models\CurrencyExchangeRate;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyExchangeRatePolicy
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

    public function view(User $user, ?CurrencyExchangeRate $rate = null): bool
    {
        return $user->can('currencies.view');
    }

    public function create(User $user): bool
    {
        return $user->can('currencies.manage');
    }

    public function update(User $user, ?CurrencyExchangeRate $rate = null): bool
    {
        return $user->can('currencies.manage');
    }

    public function delete(User $user, ?CurrencyExchangeRate $rate = null): bool
    {
        return $user->can('currencies.manage');
    }
}
