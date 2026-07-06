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
            throw new PaymentException('Paymob order creation failed.', context: [
                'gateway' => 'paymob', 'status' => $order->status(), 'body' => $order->json(),
            ]);
        }

        $orderId = (string) $order->json('id');

        // Step 3: payment key bound to the order + integration.
        $billing = $options['billing_data'] ?? [];
        $user = $payment->user;

        $key = $this->http()->post('/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => $payment->amount,
            'expiration' => 3600,
            'order_id' => $orderId,
            'currency' => $payment->currency,
            'integration_id' => (int) ($this->config['integration_id'] ?? 0),
            // Paymob requires every billing field; "NA" is its documented placeholder.
            'billing_data' => array_merge([
                'first_name' => $user?->name ?? 'NA',
                'last_name' => 'NA',
                'email' => $user?->email ?? 'na@example.com',
                'phone_number' => $user?->phone ?? 'NA',
                'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA',
                'building' => 'NA', 'shipping_method' => 'NA', 'postal_code' => 'NA',
                'city' => 'NA', 'country' => 'NA', 'state' => 'NA',
            ], $billing),
        ]);

        if ($key->failed()) {
            throw new PaymentException('Paymob payment key creation failed.', context: [
                'gateway' => 'paymob', 'status' => $key->status(), 'body' => $key->json(),
            ]);
        }

        $iframeUrl = sprintf(
            '%s/acceptance/iframes/%s?payment_token=%s',
            rtrim((string) ($this->config['base_url'] ?? ''), '/'),
            $this->config['iframe_id'] ?? '',
            $key->json('token'),
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
        $secret = (string) ($this->config['hmac_secret'] ?? '');
        $provided = (string) ($request->query('hmac') ?: $request->input('hmac', ''));

        if ($secret === '' || $provided === '') {
            return false;
        }

        $obj = $request->input('obj', []);

        $concatenated = '';
        foreach (self::HMAC_FIELDS as $field) {
            $value = data_get($obj, $field);

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $concatenated .= (string) ($value ?? '');
        }

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), $provided);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $obj = $request->input('obj', []);

        $success = filter_var($obj['success'] ?? false, FILTER_VALIDATE_BOOL);
        $pending = filter_var($obj['pending'] ?? false, FILTER_VALIDATE_BOOL);
        $refunded = filter_var($obj['is_refunded'] ?? false, FILTER_VALIDATE_BOOL);

        $status = match (true) {
            $refunded => PaymentStatus::Refunded,
            $success => PaymentStatus::Succeeded,
            $pending => PaymentStatus::Processing,
            default => PaymentStatus::Failed,
        };

        return new WebhookResult(
            gatewayReference: (string) data_get($obj, 'order.id', ''),
            status: $status,
            // Transaction id is required later for refunds.
            extra: ['transaction_id' => $obj['id'] ?? null],
            raw: $request->all(),
        );
    }

    public function refund(Payment $payment, ?Money $amount = null): PaymentResult
    {
        $transactionId = data_get($payment->metadata, 'transaction_id');

        if (! $transactionId) {
            throw new PaymentException(
                'Paymob refund requires the captured transaction id (stored from the webhook).',
                errorCode: 'refund_unavailable',
            );
        }

        $response = $this->http()->post('/acceptance/void_refund/refund', [
            'auth_token' => $this->authToken(),
            'transaction_id' => $transactionId,
            'amount_cents' => ($amount ?? $payment->remainingRefundable())->amount,
        ]);

        if ($response->failed()) {
            throw new PaymentException('Paymob refund failed.', context: [
                'gateway' => 'paymob', 'status' => $response->status(), 'body' => $response->json(),
            ]);
        }

        return new PaymentResult(
            success: true,
            status: $amount !== null && $amount->amount < $payment->amount
                ? PaymentStatus::PartiallyRefunded
                : PaymentStatus::Refunded,
            gatewayReference: (string) $response->json('id'),
            raw: $response->json() ?? [],
        );
    }

    private function authToken(): string
    {
        $response = $this->http()->post('/auth/tokens', [
            'api_key' => (string) ($this->config['api_key'] ?? ''),
        ]);

        if ($response->failed() || ! $response->json('token')) {
            throw new PaymentException('Paymob authentication failed.', context: [
                'gateway' => 'paymob', 'status' => $response->status(),
            ]);
        }

        return (string) $response->json('token');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) ($this->config['base_url'] ?? 'https://accept.paymob.com/api'))
            ->acceptJson()
            ->timeout((int) config('integrations.http.timeout', 15));
    }
}
