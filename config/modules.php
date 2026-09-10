<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Optional module toggles
    |--------------------------------------------------------------------------
    |
    | Auth, RBAC, Shared, Territory, and Currency are core primitives and are
    | always registered. Everything below is an optional capability: a
    | simple, single-purpose project can turn off what it doesn't need and
    | those modules' API routes simply won't exist — no auth/tenant
    | middleware to configure for them, no endpoints in the OpenAPI docs,
    | no client secrets to provision.
    |
    | Turning a module off here only removes its HTTP routes. Its database
    | tables, models, and internal service bindings are untouched, since
    | other modules may depend on them internally (e.g. Payment's refund
    | flow uses Governance's approval/audit contracts regardless of whether
    | Governance's own HTTP endpoints are exposed). This is safe to flip at
    | any time without a migration.
    |
    | All default to true, matching this codebase's default shape as a
    | full enterprise multi-tenant SaaS base — see the "Minimal setup"
    | section in README.md for the smallest useful combination.
    |
    */

    'payment' => env('MODULE_PAYMENT_ENABLED', true),

    'wallet' => env('MODULE_WALLET_ENABLED', true),

    'media' => env('MODULE_MEDIA_ENABLED', true),

    'webhook' => env('MODULE_WEBHOOK_ENABLED', true),

    'communication' => env('MODULE_COMMUNICATION_ENABLED', true),

    'governance' => env('MODULE_GOVERNANCE_ENABLED', true),

    // OAuth provider management (per-tenant social login config), not
    // Auth's own login/register/MFA endpoints, which always stay on.
    'integration_oauth' => env('MODULE_INTEGRATION_OAUTH_ENABLED', true),

];
