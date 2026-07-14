<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Translation Subsystem Master Switch
    |--------------------------------------------------------------------------
    |
    | Globally enable or disable automatic translations. When disabled, the
    | model saving event listener will skip job dispatching.
    |
    */
    'enabled' => env('AUTO_TRANSLATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Translation Provider
    |--------------------------------------------------------------------------
    |
    | Define the primary driver to handle translations. This resolves to a
    | driver defined in the providers array below.
    |
    */
    'default' => env('AUTO_TRANSLATION_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Chain Providers
    |--------------------------------------------------------------------------
    |
    | If the default driver throws a retryable exception (such as 429 Rate Limit
    | or 500 API Unavailable), the manager will fall back through this chain.
    |
    */
    'fallbacks' => ['logging', 'null'],

    /*
    |--------------------------------------------------------------------------
    | Translation Job Queue
    |--------------------------------------------------------------------------
    |
    | Define the connection queue for running background translation jobs.
    |
    */
    'queue' => env('AUTO_TRANSLATION_QUEUE', 'translations'),

    /*
    |--------------------------------------------------------------------------
    | Translation Cache TTL
    |--------------------------------------------------------------------------
    |
    | Duration (in seconds) that translation mappings should be kept in cache.
    | Defaults to 30 days (2592000 seconds).
    |
    */
    'cache_ttl' => env('AUTO_TRANSLATION_CACHE_TTL', 2592000),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Controls rate and failure metrics. If a provider reaches the failure
    | threshold, the circuit transitions to Open, returning fallbacks immediately.
    |
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Providers
    |--------------------------------------------------------------------------
    |
    | List of registered translation drivers. Each provider must implement the
    | TranslationProviderInterface.
    |
    */
    'providers' => [

        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_TRANSLATION_MODEL', 'gemini-1.5-flash'),
            'prompt_version' => env('GEMINI_PROMPT_VERSION', 'v1'),
            'rate_limit' => 30, // Max Requests Per Minute
        ],

        'logging' => [],

        'null' => [],

    ],

];
