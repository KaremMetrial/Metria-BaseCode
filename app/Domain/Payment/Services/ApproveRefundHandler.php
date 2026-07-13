<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Core\Exceptions\DomainException;
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
        $payment = Payment::query()->withoutGlobalScopes()->findOrFail($payload['payment_id']);

        // Guard against cross-tenant attacks: the tenant_id is stored in the
        // approval payload at request time and must match the actual payment.
        if (isset($payload['tenant_id']) && (string) $payment->tenant_id !== (string) $payload['tenant_id']) {
            throw new DomainException(
                'Approval tenant mismatch: cannot refund a payment from a different tenant.',
                errorCode: 'approval_tenant_mismatch',
            );
        }

        $this->payments->executeRefund($payment, $payload['amount'] ?? null);
    }
}
