<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Auth\Access\HandlesAuthorization;

class WalletPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('admin.super')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('wallets.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('wallets.manage');
    }

    public function view(User $user, ?Wallet $wallet = null): bool
    {
        if ($wallet === null) {
            return $user->can('wallets.view');
        }

        return (string) $user->id === (string) $wallet->user_id || $user->can('wallets.view');
    }

    public function viewTransactions(User $user, ?Wallet $wallet = null): bool
    {
        if ($wallet === null) {
            return $user->can('wallets.view');
        }

        return (string) $user->id === (string) $wallet->user_id || $user->can('wallets.view');
    }
}
