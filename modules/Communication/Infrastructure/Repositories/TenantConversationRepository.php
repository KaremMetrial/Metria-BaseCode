<?php

declare(strict_types=1);

namespace Modules\Communication\Infrastructure\Repositories;

use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Domain\Models\Membership;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;

/**
 * Keeps tenant predicates explicit in addition to Eloquent's global scope.
 * This protects command paths even if another caller later disables scopes.
 */
final class TenantConversationRepository
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function findVisible(string $id, string $actorId): ?Conversation
    {
        $tenantId = $this->tenantId();

        return Conversation::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($id)
            ->whereHas('memberships', fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('actor_id', $actorId)
                ->where('state', 'active'))
            ->first();
    }

    public function lock(string $id): ?Conversation
    {
        return Conversation::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function activeMembership(string $conversationId, string $actorId): ?Membership
    {
        return Membership::query()
            ->where('tenant_id', $this->tenantId())
            ->where('conversation_id', $conversationId)
            ->where('actor_id', $actorId)
            ->where('state', 'active')
            ->first();
    }

    private function tenantId(): string
    {
        $tenantId = $this->tenants->id();
        if (! is_string($tenantId) || $tenantId === '') {
            throw new \LogicException('COMMUNICATION_TENANT_REQUIRED');
        }

        return $tenantId;
    }
}
