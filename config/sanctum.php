<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains receive stateful (cookie-based) API
    | authentication via EnsureFrontendRequestsAreStateful. Scoped explicitly
    | through SANCTUM_STATEFUL_DOMAINS rather than left to the framework
    | default so an unreviewed origin can never ride on session cookies.
    |
    */

    'stateful' => array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'SANCTUM_STATEFUL_DOMAINS',
            'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1'
        ))
    )),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    */

    'expiration' => env('SANCTUM_TOKEN_EXPIRATION'),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
