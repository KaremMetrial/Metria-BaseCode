<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

use Illuminate\Support\Facades\Redis;

/**
 * Publishes a signed envelope to the dedicated Laravel-to-realtime transport.
 * This is deliberately separate from Socket.IO's Redis adapter channels.
 */
final class RedisRealtimePublisher implements RealtimePublisherContract
{
    /** @param array<string, mixed> $envelope */
    public function publish(array $envelope): void
    {
        if (! config('realtime.enabled')) {
            return;
        }

        $secret = (string) config('realtime.event_hmac_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('REALTIME_EVENT_HMAC_SECRET is required when real-time delivery is enabled.');
        }

        $payload = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $message = json_encode([
            'payload' => base64_encode($payload),
            'signature' => hash_hmac('sha256', $payload, $secret),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        Redis::connection((string) config('realtime.redis_connection', 'default'))
            ->publish((string) config('realtime.event_channel'), $message);
    }
}
