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

    'ai_translation_v2' => env('FEATURE_AI_TRANSLATION_V2', true),

    // 'queue_context' moved to modules/Shared/Infrastructure/config/core.php
    // ('core.queue_context_enabled') — its only consumer, QueueTenantProvider,
    // lives in the Shared module.
    // 'outbox_state_machine' moved to modules/Webhook/Infrastructure/config/webhook.php
    // ('webhook.outbox.state_machine_enabled') — its only consumer lives in Webhook.
    // 'payment_v2' moved to modules/Payment/Infrastructure/config/payments.php
    // ('payments.v2_enabled') — its only consumer, PaymentService, lives in Payment.
    // 'social_login_v2' moved to modules/Auth/Infrastructure/config/auth_features.php
    // ('auth_features.social_login_v2_enabled') — its only consumer, SocialProviderStrategy,
    // lives in the Auth module.
];
