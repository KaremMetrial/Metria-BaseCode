<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

use Modules\Auth\Domain\Events\AllSessionsRevoked;
use Modules\Auth\Domain\Events\UserSessionRevoked;
use Modules\Auth\Domain\Models\User;
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
}
