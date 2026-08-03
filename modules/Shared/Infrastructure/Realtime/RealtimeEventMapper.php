<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

use Modules\Auth\Domain\Events\AllSessionsRevoked;
use Modules\Auth\Domain\Events\UserSessionRevoked;
use Modules\Auth\Domain\Models\User;
use Modules\Communication\Domain\Events\CommunicationDomainEvent;
use Modules\Payment\Domain\Events\PaymentFailed;
use Modules\Payment\Domain\Events\PaymentRefunded;
use Modules\Payment\Domain\Events\PaymentRefundFailed;
use Modules\Payment\Domain\Events\PaymentSucceeded;
use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Wallet\Domain\Events\WalletCredited;
use Modules\Wallet\Domain\Events\WalletDebited;

/** Maps allowlisted domain events to payload-minimised public realtime events. */
final class RealtimeEventMapper
{
    /** @return array<string, mixed>|null */
    public function map(DomainEvent $event): ?array
    {
        if ($event instanceof CommunicationDomainEvent) {
            return $this->mapCommunication($event);
        }

        $subject = match (true) {
            $event instanceof PaymentSucceeded,
            $event instanceof PaymentFailed,
            $event instanceof PaymentRefunded,
            $event instanceof PaymentRefundFailed => $event->payment,
            $event instanceof WalletCredited,
            $event instanceof WalletDebited => $event->wallet,
            $event instanceof UserSessionRevoked,
            $event instanceof AllSessionsRevoked => $event->user,
            default => null,
        };

        if ($subject === null || ! is_scalar($subject->tenant_id) || (string) $subject->tenant_id === '') {
            return null; // Never turn an unscoped event into a global broadcast.
        }

        $subjectType = match (true) {
            str_starts_with($event->eventName(), 'payment.') => 'payment',
            str_starts_with($event->eventName(), 'wallet.') => 'wallet',
            default => 'user',
        };
        if ($subject instanceof User) {
            $recipientId = (string) $subject->getKey();
        } else {
            $recipientId = (string) $subject->user_id;
        }

        if ($recipientId === '') {
            return null;
        }
        $payload = match (true) {
            $event instanceof PaymentSucceeded,
            $event instanceof PaymentFailed,
            $event instanceof PaymentRefunded,
            $event instanceof PaymentRefundFailed,
            $event instanceof WalletCredited,
            $event instanceof WalletDebited,
            $event instanceof UserSessionRevoked,
            $event instanceof AllSessionsRevoked => $event->payload(),
            default => [],
        };

        return [
            'id' => $event->eventId,
            'name' => match (true) {
                $event instanceof UserSessionRevoked => 'security.session_revoked',
                $event instanceof AllSessionsRevoked => 'security.all_sessions_revoked',
                default => $event->eventName(),
            },
            'version' => 1,
            'occurred_at' => $event->occurredAt->toIso8601String(),
            'tenant_id' => (string) $subject->tenant_id,
            'audience' => [
                'type' => 'users',
                'user_ids' => [$recipientId],
            ],
            'subject' => ['type' => $subjectType, 'id' => (string) $subject->id],
            'payload' => $payload,
            'metadata' => ['correlation_id' => $event->eventId, 'causation_id' => null, 'trace_id' => null],
        ];
    }

    /** @return array<string, mixed>|null */
    private function mapCommunication(CommunicationDomainEvent $event): ?array
    {
        $payload = $event->payload();
        $tenantId = $payload['tenant_id'] ?? null;
        $conversationId = $payload['conversation_id'] ?? null;

        if (! is_string($tenantId) || ! is_string($conversationId) || $tenantId === '' || $conversationId === '') {
            return null;
        }

        $base = [
            'id' => $event->eventId,
            'name' => $event->eventName(),
            'version' => 1,
            'occurred_at' => $event->occurredAt->toIso8601String(),
            'tenant_id' => $tenantId,
            'metadata' => ['correlation_id' => $event->eventId, 'causation_id' => null, 'trace_id' => null],
        ];

        return match ($event->eventName()) {
            'communication.conversation.created' => $this->conversationCreatedEnvelope($base, $payload, $conversationId),
            'communication.message.created' => $this->messageCreatedEnvelope($base, $payload, $conversationId),
            'communication.membership.removed' => $this->membershipRemovedEnvelope($base, $payload, $conversationId),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function conversationCreatedEnvelope(array $base, array $payload, string $conversationId): ?array
    {
        $participants = $payload['participant_ids'] ?? null;
        $type = $payload['type'] ?? null;
        $state = $payload['state'] ?? null;
        $version = $payload['conversation_version'] ?? null;
        if (! is_array($participants) || ! is_string($type) || ! is_string($state) || ! is_int($version)) {
            return null;
        }

        $participantIds = array_values(array_filter($participants, 'is_string'));
        if ($participantIds === [] || count($participantIds) !== count($participants)) {
            return null;
        }

        return [
            ...$base,
            'audience' => ['type' => 'users', 'user_ids' => $participantIds],
            'subject' => ['type' => 'conversation', 'id' => $conversationId],
            'payload' => [
                'conversation_id' => $conversationId,
                'conversation_version' => $version,
                'type' => $type,
                'state' => $state,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function messageCreatedEnvelope(array $base, array $payload, string $conversationId): ?array
    {
        $messageId = $payload['message_id'] ?? null;
        $sequence = $payload['sequence'] ?? null;
        $kind = $payload['kind'] ?? null;
        $revision = $payload['revision'] ?? null;
        $authorId = $payload['author_actor_id'] ?? null;
        if (! is_string($messageId) || ! is_int($sequence) || ! is_string($kind) || ! is_int($revision) || ! is_string($authorId)) {
            return null;
        }

        return [
            ...$base,
            'audience' => ['type' => 'resource', 'resource_type' => 'conversation', 'resource_id' => $conversationId],
            'subject' => ['type' => 'message', 'id' => $messageId],
            'payload' => [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'sequence' => $sequence,
                'kind' => $kind,
                'revision' => $revision,
                'author_actor_id' => $authorId,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function membershipRemovedEnvelope(array $base, array $payload, string $conversationId): ?array
    {
        $actorId = $payload['actor_id'] ?? null;
        $version = $payload['conversation_version'] ?? null;
        if (! is_string($actorId) || ! is_int($version)) {
            return null;
        }

        return [
            ...$base,
            'audience' => ['type' => 'resource', 'resource_type' => 'conversation', 'resource_id' => $conversationId],
            'subject' => ['type' => 'membership', 'id' => $actorId],
            'payload' => [
                'conversation_id' => $conversationId,
                'actor_id' => $actorId,
                'conversation_version' => $version,
                'state' => 'left',
            ],
        ];
    }
}
