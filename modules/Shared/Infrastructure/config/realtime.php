<?php

declare(strict_types=1);

return [
    'enabled' => env('REALTIME_ENABLED', false),
    // Pub/sub channels are protocol identifiers shared with the Node service.
    // They must never inherit the application's Redis key prefix.
    'redis_connection' => env('REALTIME_REDIS_CONNECTION', 'realtime'),
    'event_channel' => env('REALTIME_EVENT_CHANNEL', 'metrial:realtime:events'),
    'event_hmac_secret' => env('REALTIME_EVENT_HMAC_SECRET'),
    'internal_secret' => env('REALTIME_INTERNAL_SECRET'),
    'session_ttl_seconds' => (int) env('REALTIME_SESSION_TTL_SECONDS', 300),
    'auth_clock_skew_seconds' => (int) env('REALTIME_AUTH_CLOCK_SKEW_SECONDS', 60),
];
