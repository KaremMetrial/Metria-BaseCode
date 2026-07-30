<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy (single database, tenant_id column strategy)
    |--------------------------------------------------------------------------
    | When enabled, models using the BelongsToTenant trait are automatically
    | scoped to the current tenant, resolved by the `tenant` middleware from
    | the request header below (or from the authenticated user).
    */
    'enabled' => env('TENANCY_ENABLED', false),

    'header' => env('TENANCY_HEADER', 'X-Tenant-ID'),
];
