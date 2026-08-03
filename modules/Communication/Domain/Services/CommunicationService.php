<?php

declare(strict_types=1);

namespace Modules\Communication\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Enums\ConversationState;
use Modules\Communication\Domain\Enums\ConversationType;
use Modules\Communication\Domain\Enums\MembershipRole;
use Modules\Communication\Domain\Enums\MembershipState;
use Modules\Communication\Domain\Events\CommunicationDomainEvent;
use Modules\Communication\Domain\Models\Conversation;
use Modules\Communication\Domain\Models\Membership;
use Modules\Communication\Domain\Models\Message;
use Modules\Communication\Domain\Models\SyncChange;
use Modules\Communication\Infrastructure\Repositories\TenantConversationRepository;
use Modules\Communication\Infrastructure\Support\MessageKindRegistry;
use Modules\Shared\Application\Exceptions\ApiException;
use Modules\Shared\Infrastructure\Events\EventBus;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;

/**
 * The Phase 2A command boundary. All mutation methods are transactional and
 * publish only durable outbox events. No realtime projection is emitted here.
 */
final class CommunicationService
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly TenantConversationRepository $conversations,
        private readonly MessageKindRegistry $messageKinds,
        private readonly EventBus $events,
    ) {}

    /** @param array<int, string> $participantIds */
    public function createConversation(User $actor, ConversationType $type, ?string $title, array $participantIds): Conversation
    {
        $tenantId = $this->tenantFor($actor);
        $participants = $this->validateProfile($actor, $type, $participantIds);

        return DB::transaction(function () use ($actor, $tenantId, $type, $title, $participants): Conversation {
            $directKey = $type === ConversationType::Direct
                ? $this->directKey((string) $actor->getKey(), $participants[0])
                : null;

            $attributes = [
                'tenant_id' => $tenantId,
                'type' => $type,
                'state' => ConversationState::Active,
                'title' => $title,
                'created_by' => (string) $actor->getKey(),
                'direct_key' => $directKey,
                'next_sequence' => 0,
                'version' => 1,
                'settings' => [],
            ];

            $conversation = $directKey === null
                ? Conversation::query()->create($attributes)
                : Conversation::query()->firstOrCreate([
                    'tenant_id' => $tenantId,
                    'type' => ConversationType::Direct,
                    'direct_key' => $directKey,
                ], $attributes);

            // The unique direct profile key is also the concurrency control:
            // firstOrCreate retries the read after a unique-key race and makes
            // every competing command converge on the same aggregate.
            if (! $conversation->wasRecentlyCreated) {
                return $conversation;
            }

            Membership::query()->create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'actor_id' => (string) $actor->getKey(),
                'role' => MembershipRole::Owner,
                'state' => MembershipState::Active,
            ]);

            foreach ($participants as $participantId) {
                Membership::query()->create([
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversation->id,
                    'actor_id' => $participantId,
                    'role' => MembershipRole::Member,
                    'state' => MembershipState::Active,
                ]);
            }

            $payload = $this->conversationPayload($conversation);
            $this->recordChange($conversation, 'conversation.created', null, $payload);
            $this->events->publish(new CommunicationDomainEvent('communication.conversation.created', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'conversation_version' => $conversation->version,
                'type' => $conversation->type->value,
                'state' => $conversation->state->value,
                'participant_ids' => array_merge([(string) $actor->getKey()], $participants),
            ]));

            return $conversation->fresh() ?? $conversation;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $content */
    public function sendMessage(User $actor, string $conversationId, string $kind, array $content, ?string $clientMessageId): Message
    {
        $tenantId = $this->tenantFor($actor);

        return DB::transaction(function () use ($actor, $conversationId, $kind, $content, $clientMessageId, $tenantId): Message {
            $conversation = $this->conversations->lock($conversationId);
            if (! $conversation instanceof Conversation) {
                throw new ApiException('Conversation not found.', 404, 'COMMUNICATION_CONVERSATION_NOT_FOUND');
            }

            $membership = $this->conversations->activeMembership($conversation->id, (string) $actor->getKey());
            if (! $membership instanceof Membership) {
                throw new ApiException('Active membership is required.', 403, 'COMMUNICATION_MEMBERSHIP_REQUIRED');
            }

            if ($conversation->state !== ConversationState::Active) {
                throw new ApiException('Conversation is not accepting messages.', 403, 'COMMUNICATION_ACTION_NOT_ALLOWED');
            }

            if ($clientMessageId !== null) {
                $existing = Message::query()
                    ->where('tenant_id', $tenantId)
                    ->where('author_actor_id', (string) $actor->getKey())
                    ->where('client_message_id', $clientMessageId)
                    ->first();
                if ($existing instanceof Message) {
                    return $existing;
                }
            }

            try {
                $canonicalContent = $this->messageKinds->validate($kind, $content);
            } catch (\InvalidArgumentException) {
                throw new ApiException('Unsupported or invalid message content.', 422, 'COMMUNICATION_MESSAGE_KIND_INVALID');
            }

            $conversation->next_sequence++;
            $conversation->version++;
            $conversation->save();

            $message = Message::query()->create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'author_actor_id' => (string) $actor->getKey(),
                'sequence' => $conversation->next_sequence,
                'kind' => $kind,
                'content' => $canonicalContent,
                'state' => 'active',
                'revision' => 1,
                'client_message_id' => $clientMessageId,
            ]);

            $payload = $this->messagePayload($message);
            $this->recordChange($conversation, 'message.created', $message->id, $payload);
            $this->events->publish(new CommunicationDomainEvent('communication.message.created', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversation->id,
                'conversation_version' => $conversation->version,
                'message_id' => $message->id,
                'sequence' => $message->sequence,
                'kind' => $message->kind->value,
                'revision' => $message->revision,
                'author_actor_id' => $message->author_actor_id,
            ]));

            return $message;
        }, attempts: 3);
    }

    /**
     * @return array{conversation: Conversation, changes: array<int, SyncChange>, cursor: string, has_more: bool}
     */
    public function synchronize(User $actor, string $conversationId, ?string $cursor, int $limit): array
    {
        $this->tenantFor($actor);
        $conversation = $this->conversations->findVisible($conversationId, (string) $actor->getKey());
        if (! $conversation instanceof Conversation) {
            throw new ApiException('Conversation not found.', 404, 'COMMUNICATION_CONVERSATION_NOT_FOUND');
        }

        $after = $this->decodeCursor($cursor);
        if ($after > $conversation->version) {
            throw new ApiException('Cursor is invalid.', 400, 'COMMUNICATION_CURSOR_INVALID');
        }

        $changes = SyncChange::query()
            ->where('tenant_id', (string) $this->tenants->id())
            ->where('conversation_id', $conversation->id)
            ->where('change_version', '>', $after)
            ->orderBy('change_version')
            ->limit($limit + 1)
            ->get();

        $hasMore = $changes->count() > $limit;
        $visible = $changes->take($limit)->values();
        $last = $visible->last();
        $lastVersion = $last instanceof SyncChange ? $last->change_version : $after;

        return [
            'conversation' => $conversation,
            'changes' => $visible->all(),
            'cursor' => $this->encodeCursor($lastVersion),
            'has_more' => $hasMore,
        ];
    }

    /** @param array<int, string> $participantIds
     * @return array<int, string>
     */
    private function validateProfile(User $actor, ConversationType $type, array $participantIds): array
    {
        $ids = array_values(array_unique(array_filter($participantIds, fn (mixed $id): bool => is_string($id) && $id !== '' && $id !== (string) $actor->getKey())));
        $count = count($ids);

        if (($type === ConversationType::Direct && $count !== 1)
            || ($type === ConversationType::PrivateGroup && ($count < 1 || $count > 99))
            || ($type === ConversationType::PrivateChannel && $count > 100)) {
            throw new ApiException('Conversation profile participant rules were not met.', 422, 'COMMUNICATION_PROFILE_INVALID');
        }

        if ($count === 0) {
            return [];
        }

        $valid = User::query()
            ->where('tenant_id', $this->tenantFor($actor))
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if (count($valid) !== $count) {
            throw new ApiException('One or more participants are unavailable.', 422, 'COMMUNICATION_PARTICIPANT_INVALID');
        }

        return $valid;
    }

    /** @param array<string, mixed> $payload */
    private function recordChange(Conversation $conversation, string $type, ?string $messageId, array $payload): void
    {
        SyncChange::query()->create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'change_version' => $conversation->version,
            'change_type' => $type,
            'message_id' => $messageId,
            'payload' => $payload,
        ]);
    }

    /** @return array<string, mixed> */
    private function conversationPayload(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'state' => $conversation->state->value,
            'title' => $conversation->title,
            'version' => $conversation->version,
            'latest_sequence' => $conversation->next_sequence,
        ];
    }

    /** @return array<string, mixed> */
    private function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'author_actor_id' => $message->author_actor_id,
            'sequence' => $message->sequence,
            'kind' => $message->kind->value,
            'content' => $message->content,
            'state' => $message->state,
            'revision' => $message->revision,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function tenantFor(User $actor): string
    {
        $tenantId = $this->tenants->id();
        if (! is_string($tenantId) || $tenantId === '' || (string) $actor->tenant_id !== $tenantId) {
            throw new ApiException('Tenant context is required.', 403, 'COMMUNICATION_TENANT_REQUIRED');
        }

        return $tenantId;
    }

    private function directKey(string $actorId, string $participantId): string
    {
        $participants = [$actorId, $participantId];
        sort($participants, SORT_STRING);

        return hash('sha256', implode('|', $participants));
    }

    private function encodeCursor(int $version): string
    {
        return rtrim(strtr(base64_encode(json_encode(['v' => $version], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decodeCursor(?string $cursor): int
    {
        if ($cursor === null || $cursor === '') {
            return 0;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if (! is_string($raw)) {
            throw new ApiException('Cursor is invalid.', 400, 'COMMUNICATION_CURSOR_INVALID');
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException('Cursor is invalid.', 400, 'COMMUNICATION_CURSOR_INVALID');
        }

        $version = is_array($decoded) ? ($decoded['v'] ?? null) : null;
        if (! is_int($version) || $version < 0) {
            throw new ApiException('Cursor is invalid.', 400, 'COMMUNICATION_CURSOR_INVALID');
        }

        return $version;
    }
}
