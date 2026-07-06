<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker defaults for outbound third-party calls
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,   // consecutive failures before opening
        'cooldown_seconds' => 60,   // how long the circuit stays open
    ],

    'http' => [
        'timeout' => 15,
        'retries' => 2,
        'retry_delay_ms' => 250,
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'default' => env('SMS_DEFAULT', 'log'),

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_FROM', 'APP'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Push notifications
    |--------------------------------------------------------------------------
    */
    'push' => [
        'fcm' => [
            'server_key' => env('FCM_SERVER_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'max_tries' => env('WEBHOOK_MAX_TRIES', 5),
        'timeout' => env('WEBHOOK_TIMEOUT', 10),
        'backoff' => [60, 300, 1800, 7200], // seconds between retries
    ],
];
