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

        $statusCodeVal = $response->json('statusCode', '');
        $statusCode = is_scalar($statusCodeVal) ? (string) $statusCodeVal : '';

        if ($response->failed() || ($statusCode !== '' && $statusCode !== '200')) {
            $statusDescVal = $response->json('statusDescription');
            $statusDesc = is_scalar($statusDescVal) ? (string) $statusDescVal : null;
            throw new PaymentException(
                $statusDesc ?? __('payments.gateway_creation_failed', ['gateway' => 'fawry']),
                context: ['gateway' => 'fawry', 'status' => $response->status(), 'body' => $response->json()],
            );
        }

        $refNumVal = $response->json('referenceNumber');
        $referenceCode = is_scalar($refNumVal) ? (string) $refNumVal : null;
        $rawResponse = $response->json();

        return new PaymentResult(
            success: true,
            status: PaymentStatus::Pending, // waits for cash payment at a Fawry point
            gatewayReference: $payment->id, // Fawry echoes merchantRefNumber in callbacks
            referenceCode: $referenceCode,
            raw: is_array($rawResponse) ? $rawResponse : [],
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
        $providedVal = $request->input('messageSignature', '');
        $provided = is_scalar($providedVal) ? (string) $providedVal : '';

        if ($secureKey === '' || $provided === '') {
            return false;
        }

        $twoDp = fn ($v): string => number_format((float) $v, 2, '.', '');

        $fawryRefNumber = $request->input('fawryRefNumber');
        $merchantRefNumber = $request->input('merchantRefNumber');
        $paymentAmount = $request->input('paymentAmount', 0);
        $orderAmount = $request->input('orderAmount', 0);
        $orderStatus = $request->input('orderStatus');
        $paymentMethod = $request->input('paymentMethod');
        $paymentRefrenceNumber = $request->input('paymentRefrenceNumber');

        $concatenated = (is_scalar($fawryRefNumber) ? (string) $fawryRefNumber : '')
            .(is_scalar($merchantRefNumber) ? (string) $merchantRefNumber : '')
            .$twoDp(is_numeric($paymentAmount) ? $paymentAmount : 0)
            .$twoDp(is_numeric($orderAmount) ? $orderAmount : 0)
            .(is_scalar($orderStatus) ? (string) $orderStatus : '')
            .(is_scalar($paymentMethod) ? (string) $paymentMethod : '')
            .(is_scalar($paymentRefrenceNumber) ? (string) $paymentRefrenceNumber : '')
            .$secureKey;

        return hash_equals(hash('sha256', $concatenated), $provided);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $orderStatusVal = $request->input('orderStatus', '');
        $orderStatusStr = is_scalar($orderStatusVal) ? (string) $orderStatusVal : '';

        $status = match (strtoupper($orderStatusStr)) {
            'PAID' => PaymentStatus::Succeeded,
            'NEW', 'UNPAID' => PaymentStatus::Pending,
            'REFUNDED' => PaymentStatus::Refunded,
            'PARTIAL_REFUNDED' => PaymentStatus::PartiallyRefunded,
            'CANCELED', 'CANCELLED' => PaymentStatus::Cancelled,
            'EXPIRED', 'FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::Processing,
        };

        $merchantRefVal = $request->input('merchantRefNumber', '');
        $merchantRef = is_scalar($merchantRefVal) ? (string) $merchantRefVal : '';
        $fawryRefNumberVal = $request->input('fawryRefNumber');

        return new WebhookResult(
            gatewayReference: $merchantRef,
            status: $status,
            extra: ['fawry_ref_number' => is_scalar($fawryRefNumberVal) ? (string) $fawryRefNumberVal : null],
            raw: $request->all(),
        );
    }

    public function refund(Payment $payment, ?Money $amount = null): PaymentResult
    {
        $merchantCode = (string) ($this->config['merchant_code'] ?? '');
        $secureKey = (string) ($this->config['secure_key'] ?? '');
        $fawryRefVal = data_get($payment->metadata, 'fawry_ref_number', '');
        $fawryRef = is_scalar($fawryRefVal) ? (string) $fawryRefVal : '';

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

        $statusCodeVal = $response->json('statusCode', '');
        $statusCode = is_scalar($statusCodeVal) ? (string) $statusCodeVal : '';

        if ($response->failed() || ($statusCode !== '' && $statusCode !== '200')) {
            $statusDescVal = $response->json('statusDescription');
            $statusDesc = is_scalar($statusDescVal) ? (string) $statusDescVal : null;
            throw new PaymentException(
                $statusDesc ?? __('payments.gateway_refund_failed', ['gateway' => 'fawry']),
                context: ['gateway' => 'fawry', 'status' => $response->status(), 'body' => $response->json()],
            );
        }

        $rawResponse = $response->json();

        return new PaymentResult(
            success: true,
            status: $amount !== null && $amount->amount < $payment->amount
                ? PaymentStatus::PartiallyRefunded
                : PaymentStatus::Refunded,
            gatewayReference: $fawryRef,
            raw: is_array($rawResponse) ? $rawResponse : [],
        );
    }

    private function http(): PendingRequest
    {
        $timeoutVal = config('integrations.http.timeout', 15);
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;

        return Http::baseUrl((string) ($this->config['base_url'] ?? 'https://atfawry.fawrystaging.com'))
            ->acceptJson()
            ->timeout($timeout);
    }
}
