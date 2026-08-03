<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Policies;

use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Enums\MembershipRole;
use Modules\Communication\Domain\Enums\MembershipState;
use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Domain\Models\Membership;

final class ConversationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->can('admin.super') ? true : null;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->activeMembership($user, $conversation);
    }

    public function create(User $user): bool
    {
        return $user->can('communication.conversations.create');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('communication.conversations.view');
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $this->activeMembership($user, $conversation)
            && $user->can('communication.messages.create');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->owner($user, $conversation) && $user->can('communication.conversations.manage');
    }

    private function activeMembership(User $user, Conversation $conversation): bool
    {
        return Membership::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('actor_id', $user->id)
            ->where('state', MembershipState::Active)
            ->exists();
    }

    private function owner(User $user, Conversation $conversation): bool
    {
        return Membership::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('actor_id', $user->id)
            ->where('state', MembershipState::Active)
            ->where('role', MembershipRole::Owner)
            ->exists();
    }
}
