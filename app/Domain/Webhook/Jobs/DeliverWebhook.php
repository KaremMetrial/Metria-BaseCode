<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Jobs;

use App\Domain\Webhook\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Signed, retried outgoing webhook delivery.
 *
 * Signature scheme (Stripe-style, documented for consumers in README):
 *   X-Webhook-Signature: t=<unix_ts>,v1=<hmac_sha256("{t}.{raw_body}", endpoint.secret)>
 *
 * Consumers should recompute the HMAC and reject stale timestamps.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public int $maxExceptions = 3;

    public function __construct(public readonly string $deliveryId) {}

    public function tries(): int
    {
        $maxTriesVal = config('integrations.webhooks.max_tries', 5);
        return is_numeric($maxTriesVal) ? (int) $maxTriesVal : 5;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        $backoffVal = config('integrations.webhooks.backoff', [60, 300, 1800, 7200]);
        $backoff = is_array($backoffVal) ? $backoffVal : [60, 300, 1800, 7200];
        /** @var array<int, int> $result */
        $result = array_map(fn ($v) => is_numeric($v) ? (int) $v : 0, $backoff);
        return $result;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->status === WebhookDelivery::STATUS_SUCCESS) {
            return; // deleted or already delivered by a previous attempt
        }

        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->active) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED, 'response_body' => 'Endpoint inactive.']);

            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        $delivery->increment('attempts');

        $appNameVal = config('app.name');
        $appName = is_string($appNameVal) ? $appNameVal : 'Laravel';
        $timeoutVal = config('integrations.webhooks.timeout', 10);
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 10;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'User-Agent' => $appName.'-Webhooks/1.0',
            'X-Webhook-Id' => $delivery->id,
            'X-Webhook-Event' => $delivery->event,
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => "t={$timestamp},v1={$signature}",
        ])
            ->timeout($timeout)
            ->withBody($body, 'application/json')
            ->post($endpoint->url);

        $delivery->update([
            'response_status' => $response->status(),
            'response_body' => mb_substr((string) $response->body(), 0, 1000),
        ]);

        if ($response->successful()) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_SUCCESS,
                'delivered_at' => now(),
            ]);

            return;
        }

        // Throwing hands the retry (with backoff) to the queue worker.
        throw new RuntimeException("Webhook delivery {$delivery->id} got HTTP {$response->status()} from {$endpoint->url}");
    }

    public function failed(Throwable $exception): void
    {
        WebhookDelivery::query()
            ->whereKey($this->deliveryId)
            ->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'response_body' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
    }
}
