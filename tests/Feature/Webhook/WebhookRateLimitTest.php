<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingress_webhooks_are_rate_limited_to_sixty_per_minute(): void
    {
        RateLimiter::clear('webhooks');

        // Send 60 requests (allowed within throttle window)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson('/api/v1/webhooks/payments/stripe', []);
            $this->assertNotEquals(429, $response->status());
        }

        // Send 61st request — must be throttled with HTTP 429 Too Many Requests
        $throttledResponse = $this->postJson('/api/v1/webhooks/payments/stripe', []);
        $throttledResponse->assertStatus(429);
    }
}
