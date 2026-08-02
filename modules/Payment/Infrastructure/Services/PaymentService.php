<?php

declare(strict_types=1);

namespace Modules\Payment\Infrastructure\Services;

use Modules\Shared\Infrastructure\Events\EventBus;
use Modules\Shared\Application\Exceptions\ApiException;
use Modules\Shared\Application\Exceptions\DomainException;
use Modules\Shared\Domain\Support\Money;
use Modules\Auth\Domain\Models\User;
use Modules\Governance\Domain\Models\ApprovalRequest;
use Modules\Governance\Infrastructure\Services\ApprovalService;
use Modules\Governance\Infrastructure\Services\AuditLogger;
use Modules\Payment\Domain\DTOs\PaymentResult;
use Modules\Payment\Domain\Enums\PaymentStatus;
use Modules\Payment\Domain\Events\PaymentFailed;
use Modules\Payment\Domain\Events\PaymentRefunded;
use Modules\Payment\Domain\Events\PaymentSucceeded;
use Modules\Payment\Domain\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Application service orchestrating the payment lifecycle:
 * create → (gateway) → webhook transition → optional maker-checker refund.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentManager $gateways,
        private readonly EventBus $events,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Persist a local Payment first (source of truth), then initiate it on
     * the gateway and store the provider reference.
     *
     * @return array{payment: Payment, result: PaymentResult}
     */
    public function create(User $user, Money $money, ?string $gateway = null, array $options = [], ?string $description = null): array
    {
        $driver = $this->gateways->driver($gateway);

        return DB::transaction(function () use ($user, $money, $driver, $options, $description) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'gateway' => $driver->name(),
                'amount' => $money->amount,
                'refunded_amount' => 0,
                'currency' => $money->currency,
                'status' => PaymentStatus::Pending,
                'description' => $description,
                'metadata' => $options['metadata'] ?? [],
            ]);

            try {
                $result = $driver->createPayment($payment, $options);
            } catch (\Throwable $e) {
                // Mark as Failed immediately so the row is not silently orphaned.
                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'gateway_error' => $e->getMessage(),
                    ]),
                ]);
                throw $e;
            }

            $payment->update([
                'gateway_reference' => $result->gatewayReference,
                'status' => $result->status,
                'metadata' => array_merge($payment->metadata ?? [], array_filter([
                    'reference_code' => $result->referenceCode,
                ])),
            ]);

            return ['payment' => $payment->refresh(), 'result' => $result];
        });
    }

    /**
     * Verify + normalise an incoming gateway webhook, then transition the
     * matching payment. Safe to call repeatedly (gateways retry): repeated
     * deliveries of the same final status are no-ops.
     */
    public function handleWebhook(string $gateway, Request $request): Payment
    {
        $driver = $this->gateways->driver($gateway);

        if (! $driver->verifyWebhook($request)) {
            throw new ApiException(__('api.invalid_signature'), status: 403, errorCode: 'invalid_signature');
        }

        $webhook = $driver->parseWebhook($request);

        /** @var Payment $payment */
        $payment = Payment::query()
            ->withoutGlobalScopes()
            ->where('gateway', $driver->name())
            ->where('gateway_reference', $webhook->gatewayReference)
            ->firstOrFail();

        app(\Modules\Shared\Infrastructure\Tenancy\TenantManager::class)->set($payment->tenant_id);

        return DB::transaction(function () use ($payment, $webhook) {
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            $previous = $payment->status;

            if ($previous === $webhook->status) {
                return $payment; // duplicate delivery
            }

            $payment->update([
                'status' => $webhook->status,
                'paid_at' => $webhook->status === PaymentStatus::Succeeded ? now() : $payment->paid_at,
                'metadata' => array_merge($payment->metadata ?? [], array_filter($webhook->extra)),
            ]);

            $this->audit->log('payment.webhook_processed', $payment, ['status' => $previous->value], ['status' => $webhook->status->value]);

            match ($webhook->status) {
                PaymentStatus::Succeeded => $this->events->publish(new PaymentSucceeded($payment)),
                PaymentStatus::Failed => $this->events->publish(new PaymentFailed($payment)),
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded => $this->events->publish(new PaymentRefunded($payment, $payment->refunded_amount)),
                default => null,
            };

            return $payment;
        });
    }

    /**
     * Maker-checker entry point: when approvals are enabled the refund is
     * queued for a second pair of eyes; otherwise it executes immediately.
     */
    public function requestRefund(Payment $payment, ?Money $amount, User $requestedBy, ?string $reason = null): ApprovalRequest|Payment
    {
        $this->assertRefundable($payment, $amount);

        if (config('governance.approvals.enabled', true)) {
            return $this->approvals->request('payments.refund', [
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,  // locked to prevent cross-tenant attacks
                'amount' => $amount?->amount,
                'reason' => $reason,
            ], $requestedBy);
        }

        return $this->executeRefund($payment, $amount?->amount);
    }

    /** Actually perform the refund on the gateway. Called by ApproveRefundHandler. */
    public function executeRefund(Payment $payment, ?int $amountMinor = null): Payment
    {
        if (! config('payments.v2_enabled', true)) {
            return DB::transaction(function () use ($payment, $amountMinor) {
                $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
                $amount = $amountMinor !== null ? Money::of($amountMinor, $payment->currency) : null;
                $this->assertRefundable($payment, $amount);

                $driver = $this->gateways->driver($payment->gateway);
                $driver->refund($payment, $amount);

                $refunded = ($amount ?? $payment->remainingRefundable())->amount;
                $newRefundedAmount = $payment->refunded_amount + $refunded;
                $status = $newRefundedAmount >= $payment->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;

                $payment->update([
                    'refunded_amount' => $newRefundedAmount,
                    'status' => $status,
                ]);

                $this->events->publish(new PaymentRefunded($payment, $refunded));

                return $payment->refresh();
            });
        }

        // Phase 1: Local DB transaction to lock row, validate, and transition to ProcessingRefund
        ['payment' => $payment, 'amount' => $amount, 'refunded' => $refunded, 'previous_status' => $previousStatus] = DB::transaction(function () use ($payment, $amountMinor) {
            $lockedPayment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            $amount = $amountMinor !== null ? Money::of($amountMinor, $lockedPayment->currency) : null;
            $this->assertRefundable($lockedPayment, $amount);

            $previousStatus = $lockedPayment->status;
            $refunded = ($amount ?? $lockedPayment->remainingRefundable())->amount;

            $lockedPayment->update(['status' => PaymentStatus::ProcessingRefund]);

            return [
                'payment' => $lockedPayment,
                'amount' => $amount,
                'refunded' => $refunded,
                'previous_status' => $previousStatus,
            ];
        });

        // Phase 2: Execute gateway API network call outside any DB row lock
        try {
            $driver = $this->gateways->driver($payment->gateway);
            $driver->refund($payment, $amount);
        } catch (\Throwable $e) {
            // Phase 3 (Failure): Saga compensation transition
            DB::transaction(function () use ($payment, $refunded, $e) {
                $lockedPayment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
                $lockedPayment->update(['status' => PaymentStatus::RefundFailed]);
                $this->events->publish(new \Modules\Payment\Domain\Events\PaymentRefundFailed($lockedPayment, $refunded, $e->getMessage()));
            });

            throw $e;
        }

        // Phase 3 (Success): Finalize refund state inside DB transaction
        return DB::transaction(function () use ($payment, $refunded) {
            $lockedPayment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            $newRefundedAmount = $lockedPayment->refunded_amount + $refunded;
            $status = $newRefundedAmount >= $lockedPayment->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;

            $lockedPayment->update([
                'refunded_amount' => $newRefundedAmount,
                'status' => $status,
            ]);

            $this->events->publish(new PaymentRefunded($lockedPayment, $refunded));

            return $lockedPayment->refresh();
        });
    }

    private function assertRefundable(Payment $payment, ?Money $amount): void
    {
        if (! in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true)) {
            throw new DomainException(__('payments.not_refundable'), errorCode: 'not_refundable');
        }

        if ($amount !== null && $amount->greaterThan($payment->remainingRefundable())) {
            throw new DomainException(__('payments.refund_exceeds_amount'), errorCode: 'refund_exceeds_amount');
        }
    }
}
