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

    public function view(User $user, Payment $payment): bool
    {
        return (string) $user->id === (string) $payment->user_id || $user->can('payments.view');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can('payments.refund');
    }
}
