<?php

declare(strict_types=1);

namespace App\Domain\Payment\Gateways;

use App\Core\Exceptions\PaymentException;
use App\Core\Support\Money;
use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\DTOs\PaymentResult;
use App\Domain\Payment\DTOs\WebhookResult;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fawry (Egypt) — charge API v2. Default flow here is PAYATFAWRY: the
 * customer receives a reference number and pays cash at any Fawry point,
 * which fits COD-heavy Egyptian e-commerce. Card flows can be added via
 * $options['payment_method'].
 *
 * Signatures are SHA-256 over documented field concatenations — verify
 * against https://developer.fawrystaging.com before production cutover.
 */
class FawryGateway implements PaymentGateway
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'fawry';
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $merchantCode = (string) ($this->config['merchant_code'] ?? '');
        $secureKey = (string) ($this->config['secure_key'] ?? '');
        $method = strtoupper((string) ($options['payment_method'] ?? 'PAYATFAWRY'));
        $customerProfileId = (string) ($options['customer_profile_id'] ?? $payment->user_id);
        $amount = $payment->money()->toDecimalString(); // "150.00"

        // signature = SHA256(merchantCode + merchantRefNum + customerProfileId
        //             + paymentMethod + amount(2dp) + secureKey)
        $signature = hash('sha256', $merchantCode.$payment->id.$customerProfileId.$method.$amount.$secureKey);

        $response = $this->http()->post('/ECommerceWeb/Fawry/payments/charge', [
            'merchantCode' => $merchantCode,
            'merchantRefNum' => $payment->id,
            'customerProfileId' => $customerProfileId,
            'customerMobile' => $options['customer_mobile'] ?? $payment->user?->phone,
            'customerEmail' => $options['customer_email'] ?? $payment->user?->email,
            'paymentMethod' => $method,
            'amount' => (float) $amount,
            'currencyCode' => $payment->currency,
            'description' => $payment->description ?? 'Payment '.$payment->id,
            'chargeItems' => [[
                'itemId' => $payment->id,
                'description' => $payment->description ?? 'Payment',
                'price' => (float) $amount,
                'quantity' => 1,
            ]],
            'signature' => $signature,
        ]);

        $statusCode = (string) $response->json('statusCode', '');

        if ($response->failed() || ($statusCode !== '' && $statusCode !== '200')) {
            throw new PaymentException(
                (string) $response->json('statusDescription', __('payments.gateway_creation_failed', ['gateway' => 'fawry'])),
                context: ['gateway' => 'fawry', 'status' => $response->status(), 'body' => $response->json()],
            );
        }

        return new PaymentResult(
            success: true,
            status: PaymentStatus::Pending, // waits for cash payment at a Fawry point
            gatewayReference: $payment->id, // Fawry echoes merchantRefNumber in callbacks
            referenceCode: (string) $response->json('referenceNumber'),
            raw: $response->json() ?? [],
        );
    }

    /**
     * Server notification (V2) signature:
     * SHA256(fawryRefNumber + merchantRefNumber + paymentAmount(2dp)
     *        + orderAmount(2dp) + orderStatus + paymentMethod
     *        + paymentRefrenceNumber(optional, omit when absent) + secureKey)
     */
    public function verifyWebhook(Request $request): bool
    {
        $secureKey = (string) ($this->config['secure_key'] ?? '');
        $provided = (string) $request->input('messageSignature', '');

        if ($secureKey === '' || $provided === '') {
            return false;
        }

        $twoDp = fn ($v): string => number_format((float) $v, 2, '.', '');

        $concatenated = $request->input('fawryRefNumber', '')
            .$request->input('merchantRefNumber', '')
            .$twoDp($request->input('paymentAmount', 0))
            .$twoDp($request->input('orderAmount', 0))
            .$request->input('orderStatus', '')
            .$request->input('paymentMethod', '')
            .($request->input('paymentRefrenceNumber') ?? '') // sic — Fawry field spelling
            .$secureKey;

        return hash_equals(hash('sha256', $concatenated), $provided);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $status = match (strtoupper((string) $request->input('orderStatus', ''))) {
            'PAID' => PaymentStatus::Succeeded,
            'NEW', 'UNPAID' => PaymentStatus::Pending,
            'REFUNDED' => PaymentStatus::Refunded,
            'PARTIAL_REFUNDED' => PaymentStatus::PartiallyRefunded,
            'CANCELED', 'CANCELLED' => PaymentStatus::Cancelled,
            'EXPIRED', 'FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::Processing,
        };

        return new WebhookResult(
            gatewayReference: (string) $request->input('merchantRefNumber', ''),
            status: $status,
            extra: ['fawry_ref_number' => $request->input('fawryRefNumber')],
            raw: $request->all(),
        );
    }

    public function refund(Payment $payment, ?Money $amount = null): PaymentResult
    {
        $merchantCode = (string) ($this->config['merchant_code'] ?? '');
        $secureKey = (string) ($this->config['secure_key'] ?? '');
        $fawryRef = (string) data_get($payment->metadata, 'fawry_ref_number', '');

        if ($fawryRef === '') {
            throw new PaymentException(
                __('payments.missing_fawry_ref'),
                errorCode: 'refund_unavailable',
            );
        }

        $refundAmount = ($amount ?? $payment->remainingRefundable())->toDecimalString();

        // signature = SHA256(merchantCode + referenceNumber + refundAmount(2dp)
        //             + refundReason(optional) + secureKey)
        $signature = hash('sha256', $merchantCode.$fawryRef.$refundAmount.$secureKey);

        $response = $this->http()->post('/ECommerceWeb/Fawry/payments/refund', [
            'merchantCode' => $merchantCode,
            'referenceNumber' => $fawryRef,
            'refundAmount' => (float) $refundAmount,
            'signature' => $signature,
        ]);

        $statusCode = (string) $response->json('statusCode', '');

        if ($response->failed() || ($statusCode !== '' && $statusCode !== '200')) {
            throw new PaymentException(
                (string) $response->json('statusDescription', __('payments.gateway_refund_failed', ['gateway' => 'fawry'])),
                context: ['gateway' => 'fawry', 'status' => $response->status(), 'body' => $response->json()],
            );
        }

        return new PaymentResult(
            success: true,
            status: $amount !== null && $amount->amount < $payment->amount
                ? PaymentStatus::PartiallyRefunded
                : PaymentStatus::Refunded,
            gatewayReference: $fawryRef,
            raw: $response->json() ?? [],
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) ($this->config['base_url'] ?? 'https://atfawry.fawrystaging.com'))
            ->acceptJson()
            ->timeout((int) config('integrations.http.timeout', 15));
    }
}
