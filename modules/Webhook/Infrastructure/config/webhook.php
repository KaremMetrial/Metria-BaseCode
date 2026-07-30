<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Outbox relay (outbox:publish command)
    |--------------------------------------------------------------------------
    */
    'outbox' => [
        'batch_size' => 100,
        'max_attempts' => 10,
        'state_machine_enabled' => env('FEATURE_OUTBOX_STATE_MACHINE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing webhook delivery
    |--------------------------------------------------------------------------
    */
    'delivery' => [
        'max_tries' => env('WEBHOOK_MAX_TRIES', 5),
        'timeout' => env('WEBHOOK_TIMEOUT', 10),
        'backoff' => [60, 300, 1800, 7200], // seconds between retries
    ],

];
