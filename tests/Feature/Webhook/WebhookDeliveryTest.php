<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Webhook\Jobs\DeliverWebhook;
use App\Domain\Webhook\Models\WebhookDelivery;
use App\Domain\Webhook\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_delivery_job_sends_http_post_with_signature_and_updates_status(): void
    {
        Http::fake([
            'example.com/*' => Http::response('OK', 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Test App',
            'url' => 'https://example.com/webhook',
            'secret' => 'whsec_test123456',
            'events' => ['payment.succeeded'],
            'active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'endpoint_id' => $endpoint->id,
            'event' => 'payment.succeeded',
            'payload' => ['payment_id' => 'pay_123', 'amount' => 5000],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        (new DeliverWebhook($delivery->id))->handle();

        Http::assertSent(function (Request $request) use ($delivery, $endpoint) {
            $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $timestamp = $request->header('X-Webhook-Timestamp')[0] ?? '';
            $expectedSig = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

            return $request->url() === 'https://example.com/webhook'
                && $request->header('X-Webhook-Id')[0] === $delivery->id
                && $request->header('X-Webhook-Event')[0] === 'payment.succeeded'
                && $request->header('X-Webhook-Signature')[0] === "t={$timestamp},v1={$expectedSig}";
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'id' => $delivery->id,
            'status' => WebhookDelivery::STATUS_SUCCESS,
            'response_status' => 200,
            'attempts' => 1,
        ]);
    }

    public function test_webhook_delivery_job_skips_inactive_endpoint(): void
    {
        Http::fake();

        $endpoint = WebhookEndpoint::create([
            'name' => 'Inactive App',
            'url' => 'https://example.com/webhook',
            'secret' => 'whsec_test123456',
            'events' => ['*'],
            'active' => false,
        ]);

        $delivery = WebhookDelivery::create([
            'endpoint_id' => $endpoint->id,
            'event' => 'payment.succeeded',
            'payload' => ['payment_id' => 'pay_123'],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        (new DeliverWebhook($delivery->id))->handle();

        Http::assertNothingSent();

        $this->assertDatabaseHas('webhook_deliveries', [
            'id' => $delivery->id,
            'status' => WebhookDelivery::STATUS_FAILED,
            'response_body' => 'Endpoint inactive.',
        ]);
    }

    public function test_webhook_delivery_job_throws_exception_on_http_error_to_trigger_retry(): void
    {
        Http::fake([
            'example.com/*' => Http::response('Server Error', 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'Failing App',
            'url' => 'https://example.com/webhook',
            'secret' => 'whsec_test123456',
            'events' => ['*'],
            'active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'endpoint_id' => $endpoint->id,
            'event' => 'payment.succeeded',
            'payload' => ['payment_id' => 'pay_123'],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Webhook delivery {$delivery->id} got HTTP 500 from https://example.com/webhook");

        try {
            (new DeliverWebhook($delivery->id))->handle();
        } finally {
            $this->assertDatabaseHas('webhook_deliveries', [
                'id' => $delivery->id,
                'response_status' => 500,
                'attempts' => 1,
            ]);
        }
    }
}
