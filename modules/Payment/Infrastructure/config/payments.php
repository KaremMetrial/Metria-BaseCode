<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway + currency
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_DEFAULT', 'stripe'),

    'currency' => env('PAYMENT_CURRENCY', 'EGP'),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    | Each gateway is a driver on the PaymentManager. Add your own by
    | implementing App\Domain\Payment\Contracts\PaymentGateway and calling
    | PaymentManager::extend('name', fn () => new YourGateway(...)).
    */
    'gateways' => [

        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => 'https://api.stripe.com/v1',
        ],

        // Paymob (Accept) — Egypt / UAE / KSA
        'paymob' => [
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
            'base_url' => 'https://accept.paymob.com/api',
        ],

        // Fawry — Egypt (cards + Fawry reference-code cash payments)
        'fawry' => [
            'base_url' => env('FAWRY_BASE_URL', 'https://atfawry.fawrystaging.com'),
            'merchant_code' => env('FAWRY_MERCHANT_CODE'),
            'secure_key' => env('FAWRY_SECURE_KEY'),
        ],

        // PayTabs — Egypt / GCC
        'paytabs' => [
            'base_url' => env('PAYTABS_BASE_URL', 'https://secure-egypt.paytabs.com'),
            'profile_id' => env('PAYTABS_PROFILE_ID'),
            'server_key' => env('PAYTABS_SERVER_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency minor units (defaults to 2 when not listed)
    |--------------------------------------------------------------------------
    */
    'minor_units' => [
        'KWD' => 3,
        'BHD' => 3,
        'OMR' => 3,
        'JPY' => 0,
        'EGP' => 2,
    ],
];
