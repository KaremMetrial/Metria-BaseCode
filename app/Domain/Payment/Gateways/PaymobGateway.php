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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Paymob "Accept" (Egypt / UAE / KSA) — classic 3-step card flow:
 *   1. POST /auth/tokens          → short-lived auth token
 *   2. POST /ecommerce/orders     → provider order (we store its id)
 *   3. POST /acceptance/payment_keys → payment key for the hosted iframe
 *
 * Verify shapes against https://docs.paymob.com before going live —
 * Paymob also offers a newer unified "Intention" API you can add as a
 * separate driver without touching callers.
 */
class PaymobGateway implements PaymentGateway
{
    /**
     * Transaction-processed callback fields included in the HMAC, in the
     * exact lexicographic order Paymob documents.
     */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured',
        'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
        'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan',
        'source_data.sub_type', 'source_data.type', 'success',
    ];

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'paymob';
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $token = $this->authToken();

        // Step 2: register the order.
        $order = $this->http()->post('/ecommerce/orders', [
            'auth_token' => $token,
            'delivery_needed' => false,
            'amount_cents' => $payment->amount,
            'currency' => $payment->currency,
            'merchant_order_id' => $payment->id,
            'items' => [],
        ]);

        if ($order->failed()) {
            throw new PaymentException(__('payments.gateway_creation_failed', ['gateway' => 'paymob']), context: [
                'gateway' => 'paymob', 'status' => $order->status(), 'body' => $order->json(),
            ]);
        }

        $orderIdVal = $order->json('id');
        $orderId = is_scalar($orderIdVal) ? (string) $orderIdVal : '';

        // Step 3: payment key bound to the order + integration.
        $billingVal = $options['billing_data'] ?? [];
        $billing = is_array($billingVal) ? $billingVal : [];
        $user = $payment->user;
        $integrationIdVal = $this->config['integration_id'] ?? 0;
        $integrationId = is_numeric($integrationIdVal) ? (int) $integrationIdVal : 0;

        $key = $this->http()->post('/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => $payment->amount,
            'expiration' => 3600,
            'order_id' => $orderId,
            'currency' => $payment->currency,
            'integration_id' => $integrationId,
            // Paymob requires every billing field; "NA" is its documented placeholder.
            'billing_data' => array_merge([
                'first_name' => $user !== null ? $user->name : 'NA',
                'last_name' => 'NA',
                'email' => $user !== null ? $user->email : 'na@example.com',
                'phone_number' => $user !== null ? ($user->phone ?? 'NA') : 'NA',
                'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA',
                'building' => 'NA', 'shipping_method' => 'NA', 'postal_code' => 'NA',
                'city' => 'NA', 'country' => 'NA', 'state' => 'NA',
            ], $billing),
        ]);

        if ($key->failed()) {
            throw new PaymentException(__('payments.gateway_creation_failed', ['gateway' => 'paymob']), context: [
                'gateway' => 'paymob', 'status' => $key->status(), 'body' => $key->json(),
            ]);
        }

        $baseUrlVal = $this->config['base_url'] ?? '';
        $baseUrl = is_string($baseUrlVal) ? $baseUrlVal : '';
        $iframeIdVal = $this->config['iframe_id'] ?? '';
        $iframeId = is_scalar($iframeIdVal) ? (string) $iframeIdVal : '';
        $tokenVal = $key->json('token');
        $tokenStr = is_scalar($tokenVal) ? (string) $tokenVal : '';

        $iframeUrl = sprintf(
            '%s/acceptance/iframes/%s?payment_token=%s',
            rtrim($baseUrl, '/'),
            $iframeId,
            $tokenStr,
        );

        return new PaymentResult(
            success: true,
            status: PaymentStatus::Processing,
            gatewayReference: $orderId, // callbacks correlate via order id
            redirectUrl: $iframeUrl,
            raw: ['order_id' => $orderId],
        );
    }

    /**
     * HMAC-SHA512 of the documented fields (lexicographic order, dot
     * notation for nested), compared to the `hmac` query/body parameter.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secretVal = $this->config['hmac_secret'] ?? '';
        $secret = is_scalar($secretVal) ? (string) $secretVal : '';
        $providedQuery = $request->query('hmac');
        $providedInput = $request->input('hmac', '');
        $providedVal = is_string($providedQuery) && $providedQuery !== '' ? $providedQuery : $providedInput;
        $provided = is_scalar($providedVal) ? (string) $providedVal : '';

        if ($secret === '' || $provided === '') {
            return false;
        }

        $objVal = $request->input('obj', []);
        $obj = is_array($objVal) ? $objVal : [];

        $concatenated = '';
        foreach (self::HMAC_FIELDS as $field) {
            $value = data_get($obj, $field);

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $concatenated .= is_scalar($value) ? (string) $value : '';
        }

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), $provided);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $objVal = $request->input('obj', []);
        $obj = is_array($objVal) ? $objVal : [];

        $successVal = $obj['success'] ?? false;
        $success = filter_var(is_scalar($successVal) ? $successVal : false, FILTER_VALIDATE_BOOL);
        $pendingVal = $obj['pending'] ?? false;
        $pending = filter_var(is_scalar($pendingVal) ? $pendingVal : false, FILTER_VALIDATE_BOOL);
        $refundedVal = $obj['is_refunded'] ?? false;
        $refunded = filter_var(is_scalar($refundedVal) ? $refundedVal : false, FILTER_VALIDATE_BOOL);

        $status = match (true) {
            $refunded => PaymentStatus::Refunded,
            $success => PaymentStatus::Succeeded,
            $pending => PaymentStatus::Processing,
            default => PaymentStatus::Failed,
        };

        $orderIdVal = data_get($obj, 'order.id', '');
        $orderId = is_scalar($orderIdVal) ? (string) $orderIdVal : '';

        $transactionIdVal = $obj['id'] ?? null;
        $transactionId = is_scalar($transactionIdVal) ? (string) $transactionIdVal : null;

        return new WebhookResult(
            gatewayReference: $orderId,
            status: $status,
            // Transaction id is required later for refunds.
            extra: ['transaction_id' => $transactionId],
            raw: $request->all(),
        );
    }

    public function refund(Payment $payment, ?Money $amount = null): PaymentResult
    {
        $transactionIdVal = data_get($payment->metadata, 'transaction_id');
        $transactionId = is_scalar($transactionIdVal) ? (string) $transactionIdVal : '';

        if ($transactionId === '') {
            throw new PaymentException(
                __('payments.missing_transaction_id'),
                errorCode: 'refund_unavailable',
            );
        }

        $response = $this->http()->post('/acceptance/void_refund/refund', [
            'auth_token' => $this->authToken(),
            'transaction_id' => $transactionId,
            'amount_cents' => ($amount ?? $payment->remainingRefundable())->amount,
        ]);

        if ($response->failed()) {
            throw new PaymentException(__('payments.gateway_refund_failed', ['gateway' => 'paymob']), context: [
                'gateway' => 'paymob', 'status' => $response->status(), 'body' => $response->json(),
            ]);
        }

        $idVal = $response->json('id');
        $id = is_scalar($idVal) ? (string) $idVal : '';
        $rawResponse = $response->json();

        return new PaymentResult(
            success: true,
            status: $amount !== null && $amount->amount < $payment->amount
                ? PaymentStatus::PartiallyRefunded
                : PaymentStatus::Refunded,
            gatewayReference: $id,
            raw: is_array($rawResponse) ? $rawResponse : [],
        );
    }

    private function authToken(): string
    {
        return Cache::remember('paymob_auth_token', 3300, function () {
            $apiKeyVal = $this->config['api_key'] ?? '';
            $apiKey = is_scalar($apiKeyVal) ? (string) $apiKeyVal : '';
            $response = $this->http()->post('/auth/tokens', [
                'api_key' => $apiKey,
            ]);

            if ($response->failed() || ! $response->json('token')) {
                throw new PaymentException(__('payments.gateway_auth_failed', ['gateway' => 'paymob']), context: [
                    'gateway' => 'paymob', 'status' => $response->status(),
                ]);
            }

            $tokenVal = $response->json('token');
            return is_scalar($tokenVal) ? (string) $tokenVal : '';
        });
    }

    private function http(): PendingRequest
    {
        $timeoutVal = config('integrations.http.timeout', 15);
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;
        $baseUrlVal = $this->config['base_url'] ?? 'https://accept.paymob.com/api';
        $baseUrl = is_scalar($baseUrlVal) ? (string) $baseUrlVal : 'https://accept.paymob.com/api';

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout($timeout);
    }
}
