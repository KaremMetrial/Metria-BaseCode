<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags govern the phased rollout of major architectural transitions
    | across the multi-tenant modular monolith. They allow zero-downtime
    | canary releases and instant rollback of behavioral changes.
    |
    */

    'payment_v2' => env('FEATURE_PAYMENT_V2', true),
    'queue_context' => env('FEATURE_QUEUE_CONTEXT', true),
    'social_login_v2' => env('FEATURE_SOCIAL_LOGIN_V2', false),
    'outbox_state_machine' => env('FEATURE_OUTBOX_STATE_MACHINE', true),
    'ai_translation_v2' => env('FEATURE_AI_TRANSLATION_V2', true),
];
