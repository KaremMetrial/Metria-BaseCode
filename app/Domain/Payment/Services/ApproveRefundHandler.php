<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Payment\Models\Payment;

/**
 * Invokable executed by ApprovalService when a `payments.refund` approval
 * is granted (registered in config/governance.php → approvals.handlers).
 */
class ApproveRefundHandler
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(array $payload, ApprovalRequest $request): void
    {
        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($payload['payment_id']);

        $this->payments->executeRefund($payment, $payload['amount'] ?? null);
    }
}
