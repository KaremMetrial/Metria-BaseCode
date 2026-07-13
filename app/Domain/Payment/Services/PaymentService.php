<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Core\Events\EventBus;
use App\Core\Exceptions\ApiException;
use App\Core\Exceptions\DomainException;
use App\Core\Support\Money;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Governance\Services\ApprovalService;
use App\Domain\Governance\Services\AuditLogger;
use App\Domain\Payment\DTOs\PaymentResult;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Events\PaymentFailed;
use App\Domain\Payment\Events\PaymentRefunded;
use App\Domain\Payment\Events\PaymentSucceeded;
use App\Domain\Payment\Models\Payment;
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
                'user_id'     => $user->id,
                'gateway'     => $driver->name(),
                'amount'      => $money->amount,
                'refunded_amount' => 0,
                'currency'    => $money->currency,
                'status'      => PaymentStatus::Pending,
                'description' => $description,
                'metadata'    => $options['metadata'] ?? [],
            ]);

            try {
                $result = $driver->createPayment($payment, $options);
            } catch (\Throwable $e) {
                // Mark as Failed immediately so the row is not silently orphaned.
                $payment->update([
                    'status'   => PaymentStatus::Failed,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'gateway_error' => $e->getMessage(),
                    ]),
                ]);
                throw $e;
            }

            $payment->update([
                'gateway_reference' => $result->gatewayReference,
                'status'            => $result->status,
                'metadata'          => array_merge($payment->metadata ?? [], array_filter([
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
                'tenant_id'  => $payment->tenant_id,  // locked to prevent cross-tenant attacks
                'amount'     => $amount?->amount,
                'reason'     => $reason,
            ], $requestedBy);
        }

        return $this->executeRefund($payment, $amount?->amount);
    }

    /** Actually perform the refund on the gateway. Called by ApproveRefundHandler. */
    public function executeRefund(Payment $payment, ?int $amountMinor = null): Payment
    {
        return DB::transaction(function () use ($payment, $amountMinor) {
            // Acquire the row lock FIRST so concurrent refund requests serialize here.
            $payment = Payment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);

            $amount = $amountMinor !== null ? Money::of($amountMinor, $payment->currency) : null;

            // Re-validate under the lock — a concurrent request may have already
            // changed the refunded_amount between the caller's check and this point.
            $this->assertRefundable($payment, $amount);

            $driver = $this->gateways->driver($payment->gateway);
            $driver->refund($payment, $amount);

            $refunded        = ($amount ?? $payment->remainingRefundable())->amount;
            $newRefundedAmount = $payment->refunded_amount + $refunded;

            $status = $newRefundedAmount >= $payment->amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;

            $payment->update([
                'refunded_amount' => $newRefundedAmount,
                'status'          => $status,
            ]);

            $this->events->publish(new PaymentRefunded($payment, $refunded));

            return $payment->refresh();
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
