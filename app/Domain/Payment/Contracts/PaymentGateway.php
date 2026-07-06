<?php

declare(strict_types=1);

namespace App\Domain\Payment\Contracts;

use App\Core\Support\Money;
use App\Domain\Payment\DTOs\PaymentResult;
use App\Domain\Payment\DTOs\WebhookResult;
use App\Domain\Payment\Models\Payment;
use Illuminate\Http\Request;

/**
 * Strategy contract every payment provider implements. The PaymentManager
 * resolves the right driver at runtime, so application code never touches
 * a provider SDK or HTTP call directly.
 */
interface PaymentGateway
{
    /** Driver name as registered on the PaymentManager (stripe, paymob, ...). */
    public function name(): string;

    /**
     * Initiate a payment with the provider for an already-persisted local
     * Payment row. Implementations must set/return the provider reference
     * used later to correlate webhooks.
     *
     * @param  array  $options  gateway-specific extras (return_url, customer data, method, ...)
     */
    public function createPayment(Payment $payment, array $options = []): PaymentResult;

    /** Authenticate an incoming webhook (HMAC / signature / re-query). */
    public function verifyWebhook(Request $request): bool;

    /** Translate a verified webhook into a normalised WebhookResult. */
    public function parseWebhook(Request $request): WebhookResult;

    /** Full refund when $amount is null, partial otherwise. */
    public function refund(Payment $payment, ?Money $amount = null): PaymentResult;
}
