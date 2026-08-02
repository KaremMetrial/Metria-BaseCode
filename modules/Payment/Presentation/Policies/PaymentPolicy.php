<?php

declare(strict_types=1);

namespace App\Domain\Payment\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Payment\Models\Payment;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
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
        return $user->can('payments.view') || $user->can('payments.create');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function manage(User $user): bool
    {
        return $user->can('payments.manage');
    }

    public function view(User $user, ?Payment $payment = null): bool
    {
        if ($payment === null) {
            return $user->can('payments.view');
        }

        return (string) $user->id === (string) $payment->user_id || $user->can('payments.view');
    }

    public function refund(User $user, ?Payment $payment = null): bool
    {
        return $user->can('payments.refund');
    }
}
