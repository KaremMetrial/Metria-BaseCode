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
 * PayTabs (Egypt / GCC) — hosted payment page via /payment/request.
 * Region matters: the base_url must match the profile's region
 * (secure-egypt, secure, secure-global, ...). See https://site.paytabs.com/en/developers
 *
 * Webhook verification: PayTabs signs callbacks with HMAC-SHA256 of the raw
 * body using the server key (Signature header). As defence in depth we ALSO
 * re-query /payment/query — the provider's documented source of truth.
 */
class PaytabsGateway implements PaymentGateway
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'paytabs';
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $response = $this->http()->post('/payment/request', [
            'profile_id' => (int) ($this->config['profile_id'] ?? 0),
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $payment->id,
            'cart_currency' => $payment->currency,
            'cart_amount' => (float) $payment->money()->toDecimalString(),
            'cart_description' => $payment->description ?? 'Payment '.$payment->id,
            'callback' => $options['callback_url'] ?? route('webhooks.payments', ['gateway' => 'paytabs']),
            'return' => $options['return_url'] ?? config('app.url'),
            'customer_details' => [
                'name' => $payment->user !== null ? $payment->user->name : 'NA',
                'email' => $payment->user !== null ? $payment->user->email : 'na@example.com',
                'phone' => $payment->user !== null ? ($payment->user->phone ?? 'NA') : 'NA',
                'street1' => 'NA', 'city' => 'NA', 'country' => 'EG', 'ip' => request()->ip(),
            ],
        ]);

        if ($response->failed() || ! $response->json('tran_ref')) {
            $msgVal = $response->json('message');
            $msg = is_scalar($msgVal) ? (string) $msgVal : null;
            throw new PaymentException(
                $msg ?? __('payments.gateway_creation_failed', ['gateway' => 'paytabs']),
                context: ['gateway' => 'paytabs', 'status' => $response->status(), 'body' => $response->json()],
            );
        }

        $tranRefVal = $response->json('tran_ref');
        $tranRef = is_scalar($tranRefVal) ? (string) $tranRefVal : '';
        $redirectUrlVal = $response->json('redirect_url');
        $redirectUrl = is_scalar($redirectUrlVal) ? (string) $redirectUrlVal : '';
        $rawResponse = $response->json();

        return new PaymentResult(
            success: true,
            status: PaymentStatus::Processing,
            gatewayReference: $tranRef,
            redirectUrl: $redirectUrl,
            raw: is_array($rawResponse) ? $rawResponse : [],
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $serverKey = (string) ($this->config['server_key'] ?? '');
        $signature = (string) $request->header('Signature', '');

        if ($serverKey === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $serverKey), $signature);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $tranRefVal = $request->input('tran_ref', '');
        $tranRef = is_scalar($tranRefVal) ? (string) $tranRefVal : '';

        // Defence in depth: trust the queried state, not the pushed payload.
        $query = $this->http()->post('/payment/query', [
            'profile_id' => (int) ($this->config['profile_id'] ?? 0),
            'tran_ref' => $tranRef,
        ]);

        $rawQuery = $query->json();
        $data = $query->successful() && is_array($rawQuery) ? $rawQuery : $request->all();

        // response_status: A=authorised, H=hold, P=pending, V=voided,
        // E=error, D=declined, X=expired (per PayTabs transaction API docs).
        $respStatusVal = data_get($data, 'payment_result.response_status', '');
        $respStatus = is_scalar($respStatusVal) ? (string) $respStatusVal : '';
        $status = match (strtoupper($respStatus)) {
            'A' => PaymentStatus::Succeeded,
            'H', 'P' => PaymentStatus::Processing,
            'V' => PaymentStatus::Refunded,
            'X' => PaymentStatus::Cancelled,
            default => PaymentStatus::Failed,
        };

        return new WebhookResult(
            gatewayReference: $tranRef,
            status: $status,
            extra: ['cart_id' => data_get($data, 'cart_id')],
            raw: $data,
        );
    }

    public function refund(Payment $payment, ?Money $amount = null): PaymentResult
    {
        $refund = $amount ?? $payment->remainingRefundable();

        $response = $this->http()->post('/payment/request', [
            'profile_id' => (int) ($this->config['profile_id'] ?? 0),
            'tran_type' => 'refund',
            'tran_class' => 'ecom',
            'tran_ref' => $payment->gateway_reference,
            'cart_id' => $payment->id.'-refund-'.now()->timestamp,
            'cart_currency' => $payment->currency,
            'cart_amount' => (float) $refund->toDecimalString(),
            'cart_description' => 'Refund for '.$payment->id,
        ]);

        $jsonResponse = $response->json();
        $respStatusVal = is_array($jsonResponse) ? data_get($jsonResponse, 'payment_result.response_status', '') : '';
        $respStatus = is_scalar($respStatusVal) ? (string) $respStatusVal : '';

        $ok = $response->successful() && strtoupper($respStatus) === 'A';

        if (! $ok) {
            $respMsgVal = is_array($jsonResponse) ? data_get($jsonResponse, 'payment_result.response_message') : null;
            $respMsg = is_scalar($respMsgVal) ? (string) $respMsgVal : null;
            throw new PaymentException(
                $respMsg ?? __('payments.gateway_refund_failed', ['gateway' => 'paytabs']),
                context: ['gateway' => 'paytabs', 'status' => $response->status(), 'body' => $jsonResponse],
            );
        }

        $refundTranRefVal = $response->json('tran_ref');
        $refundTranRef = is_scalar($refundTranRefVal) ? (string) $refundTranRefVal : '';
        $rawRefundJson = $response->json();

        return new PaymentResult(
            success: true,
            status: $refund->amount < $payment->amount
                ? PaymentStatus::PartiallyRefunded
                : PaymentStatus::Refunded,
            gatewayReference: $refundTranRef,
            raw: is_array($rawRefundJson) ? $rawRefundJson : [],
        );
    }

    private function http(): PendingRequest
    {
        $timeoutVal = config('integrations.http.timeout', 15);
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;

        return Http::baseUrl((string) ($this->config['base_url'] ?? 'https://secure-egypt.paytabs.com'))
            ->withHeaders(['authorization' => (string) ($this->config['server_key'] ?? '')])
            ->acceptJson()
            ->timeout($timeout);
    }
}
