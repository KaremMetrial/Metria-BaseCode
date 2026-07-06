<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    | Locales the API accepts via ?lang=, the Accept-Language header, or the
    | authenticated user's stored preference. Anything else falls back.
    */
    'supported' => explode(',', env('SUPPORTED_LOCALES', 'en,ar')),

    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | RTL locales
    |--------------------------------------------------------------------------
    | Returned in API meta so clients can flip layout direction.
    */
    'rtl' => ['ar', 'fa', 'ur', 'he'],
];
