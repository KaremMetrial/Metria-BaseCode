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
 * Stripe via raw REST (no SDK dependency): PaymentIntents + webhooks.
 * NOTE: endpoint shapes are current as of writing — always verify against
 * https://docs.stripe.com/api when upgrading.
 */
class StripeGateway implements PaymentGateway
{
    /** Reject webhooks whose timestamp drifts more than this (replay guard). */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function createPayment(Payment $payment, array $options = []): PaymentResult
    {
        $response = $this->http()->asForm()->post('/payment_intents', [
            'amount' => $payment->amount,
            'currency' => strtolower($payment->currency),
            'description' => $payment->description,
            'metadata' => ['payment_id' => $payment->id],
            'automatic_payment_methods' => ['enabled' => 'true'],
        ]);

        if ($response->failed()) {
            $errVal = $response->json('error.message');
            $errMsg = is_scalar($errVal) ? (string) $errVal : null;
            throw new PaymentException(
                $errMsg ?? __('payments.gateway_creation_failed', ['gateway' => 'stripe']),
                context: ['gateway' => 'stripe', 'status' => $response->status()],
            );
        }

        $idVal = $response->json('id');
        $id = is_scalar($idVal) ? (string) $idVal : '';

        $clientSecretVal = $response->json('client_secret');
        $clientSecret = is_scalar($clientSecretVal) ? (string) $clientSecretVal : '';

        $rawResponse = $response->json();

        return new PaymentResult(
            success: true,
            status: PaymentStatus::Processing,
            gatewayReference: $id,
            clientSecret: $clientSecret,
            raw: is_array($rawResponse) ? $rawResponse : [],
        );
    }

    /**
     * Stripe-Signature: t=<ts>,v1=<hmac>[,v1=...]
     * signed_payload = "{t}.{raw_body}", HMAC-SHA256 with the webhook secret.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $header = (string) $request->header('Stripe-Signature', '');

        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $eventVal = $request->json()->all();
        $event = is_array($eventVal) ? $eventVal : [];
        $dataVal = $event['data'] ?? [];
        $dataArr = is_array($dataVal) ? $dataVal : [];
        $objectVal = isset($dataArr['object']) && is_array($dataArr['object']) ? $dataArr['object'] : [];

        $status = match ($event['type'] ?? '') {
            'payment_intent.succeeded' => PaymentStatus::Succeeded,
            'payment_intent.payment_failed' => PaymentStatus::Failed,
            'payment_intent.canceled' => PaymentStatus::Cancelled,
            'charge.refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };

        $gatewayRefVal = $objectVal['payment_intent'] ?? $objectVal['id'] ?? '';
        $gatewayRef = is_scalar($gatewayRefVal) ? (string) $gatewayRefVal : '';

        $latestChargeVal = $objectVal['latest_charge'] ?? null;
        $latestCharge = is_scalar($latestChargeVal) ? (string) $latestChargeVal : null;

        return new WebhookResult(
            gatewayReference: $gatewayRef,
            status: $status,
            extra: ['latest_charge' => $latestCharge],
            raw: $event,
        );
    }

    public function refund(Payment $payment, ?Money $amount = null): PaymentResult
    {
        $body = ['payment_intent' => $payment->gateway_reference];

        if ($amount !== null) {
            $body['amount'] = $amount->amount;
        }

        $response = $this->http()->asForm()->post('/refunds', $body);

        if ($response->failed()) {
            $errVal = $response->json('error.message');
            $errMsg = is_scalar($errVal) ? (string) $errVal : null;
            throw new PaymentException(
                $errMsg ?? __('payments.gateway_refund_failed', ['gateway' => 'stripe']),
                context: ['gateway' => 'stripe', 'status' => $response->status()],
            );
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

    private function http(): PendingRequest
    {
        $baseUrlVal = $this->config['base_url'] ?? 'https://api.stripe.com/v1';
        $baseUrl = is_string($baseUrlVal) ? $baseUrlVal : 'https://api.stripe.com/v1';

        $timeoutVal = config('integrations.http.timeout', 15);
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;

        return Http::baseUrl($baseUrl)
            ->withToken((string) ($this->config['secret_key'] ?? ''))
            ->timeout($timeout);
    }
}
