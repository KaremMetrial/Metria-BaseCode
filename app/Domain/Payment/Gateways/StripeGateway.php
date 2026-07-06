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
            throw new PaymentException(
                $response->json('error.message', 'Stripe payment creation failed.'),
                context: ['gateway' => 'stripe', 'status' => $response->status()],
            );
        }

        return new PaymentResult(
            success: true,
            status: PaymentStatus::Processing,
            gatewayReference: $response->json('id'),
            clientSecret: $response->json('client_secret'),
            raw: $response->json() ?? [],
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
        $event = $request->json()->all();
        $object = $event['data']['object'] ?? [];

        $status = match ($event['type'] ?? '') {
            'payment_intent.succeeded' => PaymentStatus::Succeeded,
            'payment_intent.payment_failed' => PaymentStatus::Failed,
            'payment_intent.canceled' => PaymentStatus::Cancelled,
            'charge.refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Processing,
        };

        return new WebhookResult(
            gatewayReference: (string) ($object['payment_intent'] ?? $object['id'] ?? ''),
            status: $status,
            extra: ['latest_charge' => $object['latest_charge'] ?? null],
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
            throw new PaymentException(
                $response->json('error.message', 'Stripe refund failed.'),
                context: ['gateway' => 'stripe', 'status' => $response->status()],
            );
        }

        return new PaymentResult(
            success: true,
            status: $amount !== null && $amount->amount < $payment->amount
                ? PaymentStatus::PartiallyRefunded
                : PaymentStatus::Refunded,
            gatewayReference: $response->json('id'),
            raw: $response->json() ?? [],
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'] ?? 'https://api.stripe.com/v1')
            ->withToken((string) ($this->config['secret_key'] ?? ''))
            ->timeout((int) config('integrations.http.timeout', 15));
    }
}
