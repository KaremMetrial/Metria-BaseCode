<?php

declare(strict_types=1);

namespace Modules\Payment\Infrastructure\Services;

use Modules\Shared\Application\Exceptions\DomainException;
use Modules\Governance\Domain\Models\ApprovalRequest;
use Modules\Payment\Domain\Models\Payment;

/**
 * Invokable executed by ApprovalService when a `payments.refund` approval
 * is granted (registered in config/governance.php → approvals.handlers).
 */
class ApproveRefundHandler
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload, ApprovalRequest $request): void
    {
        /** @var Payment $payment */
        $payment = Payment::query()->withoutGlobalScopes()->findOrFail($payload['payment_id']);

        // Guard against cross-tenant attacks: the tenant_id is stored in the
        // approval payload at request time and must match the actual payment.
        $paymentTenantVal = $payment->tenant_id;
        $paymentTenant = is_scalar($paymentTenantVal) ? (string) $paymentTenantVal : '';
        $payloadTenantVal = $payload['tenant_id'] ?? null;
        $payloadTenant = is_scalar($payloadTenantVal) ? (string) $payloadTenantVal : '';
        if ($payloadTenant !== '' && $paymentTenant !== $payloadTenant) {
            throw new DomainException(
                'Approval tenant mismatch: cannot refund a payment from a different tenant.',
                errorCode: 'approval_tenant_mismatch',
            );
        }

        $amountVal = $payload['amount'] ?? null;
        $amountMinor = is_numeric($amountVal) ? (int) $amountVal : null;
        $this->payments->executeRefund($payment, $amountMinor);
    }
}
