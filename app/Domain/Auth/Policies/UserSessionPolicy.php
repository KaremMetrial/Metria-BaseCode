<?php

declare(strict_types=1);

namespace App\Domain\Auth\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserSessionPolicy
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
        return $user->can('sessions.view');
    }

    public function view(User $user, ?UserSession $session = null): bool
    {
        return ($session !== null && (string) $user->id === (string) $session->user_id) || $user->can('sessions.view');
    }

    public function delete(User $user, ?UserSession $session = null): bool
    {
        return ($session !== null && (string) $user->id === (string) $session->user_id) || $user->can('sessions.manage');
    }
}
