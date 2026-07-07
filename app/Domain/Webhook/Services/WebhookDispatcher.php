<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Domain\Webhook\Jobs\DeliverWebhook;
use App\Domain\Webhook\Models\WebhookDelivery;
use App\Domain\Webhook\Models\WebhookEndpoint;

/**
 * Fan-out half of the outbox relay: one delivery row + one queued job per
 * subscribed endpoint. Delivery state, retries and responses are tracked
 * per endpoint so a flaky consumer never blocks the others.
 */
class WebhookDispatcher
{
    public function dispatch(string $event, array $payload): void
    {
        WebhookEndpoint::query()
            ->withoutGlobalScopes() // outbox relay runs outside any tenant context
            ->where('active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->listensTo($event))
            ->each(function (WebhookEndpoint $endpoint) use ($event, $payload) {
                $delivery = WebhookDelivery::create([
                    'endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'payload' => $payload,
                    'status' => WebhookDelivery::STATUS_PENDING,
                    'attempts' => 0,
                ]);

                DeliverWebhook::dispatch($delivery->id);
            });
    }
}
