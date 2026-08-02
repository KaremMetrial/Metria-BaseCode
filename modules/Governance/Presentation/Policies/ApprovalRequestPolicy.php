<?php

declare(strict_types=1);

namespace App\Domain\Governance\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Governance\Models\ApprovalRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovalRequestPolicy
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
        return $user->can('governance.approvals.view');
    }

    public function view(User $user, ?ApprovalRequest $approvalRequest = null): bool
    {
        return ($approvalRequest !== null && (string) $user->id === (string) $approvalRequest->requested_by) || $user->can('governance.approvals.view');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ?ApprovalRequest $approvalRequest = null): bool
    {
        return $user->can('governance.approvals.decide');
    }

    public function decide(User $user, ?ApprovalRequest $approvalRequest = null): bool
    {
        return $user->can('governance.approvals.decide');
    }

    public function delete(User $user, ?ApprovalRequest $approvalRequest = null): bool
    {
        return $user->can('governance.approvals.decide');
    }
}
