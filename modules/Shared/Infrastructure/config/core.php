<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */
    'api' => [
        'version' => 'v1',
        'per_page' => 20,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    | Fallback currency used by Modules\Shared\Domain\Support\Money when none
    | is given explicitly. Reuses PAYMENT_CURRENCY so existing environments
    | don't need a new variable — same env var backs config('payments.currency')
    | for the (still host-owned) Payment/Currency domains.
    */
    'money' => [
        'default_currency' => env('PAYMENT_CURRENCY', 'EGP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    | Owned here because IdempotencyMiddleware and the IdempotencyKey model
    | are both Shared infrastructure. Governance's prune command reads this
    | same key for its retention housekeeping.
    */
    'idempotency' => [
        'header' => 'Idempotency-Key',
        'ttl_hours' => env('IDEMPOTENCY_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue tenant context
    |--------------------------------------------------------------------------
    */
    'queue_context_enabled' => env('FEATURE_QUEUE_CONTEXT', true),

];
