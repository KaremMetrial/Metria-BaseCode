<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Decimal Precision & Scale
    |--------------------------------------------------------------------------
    |
    | Defines the database storage precision (total digits) and scale (decimal
    | digits) for stored exchange rates. Increase scale for cryptocurrency
    | or micro-hedging support.
    |
    */
    'precision' => 24,
    'scale' => 14,

    /*
    |--------------------------------------------------------------------------
    | Stale Rate Policy
    |--------------------------------------------------------------------------
    |
    | Defines the maximum threshold (in hours) an exchange rate remains valid
    | after its scheduled expiration. Beyond this threshold, rates are
    | considered stale.
    |
    */
    'stale_rate_threshold_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Default Conversion Algorithm and Rounding
    |--------------------------------------------------------------------------
    |
    | 'default_rounding_mode' supports:
    |   - 'half_up'  : Standard mathematical rounding.
    |   - 'half_even': Banker's rounding (rounds to the nearest even number).
    |   - 'down'     : Truncates decimal values.
    |
    | 'default_algorithm_version' is recorded on payment snapshots to ensure
    | backward compatibility of calculation methods over time.
    |
    */
    'default_rounding_mode' => 'half_even',
    'default_algorithm_version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Active Providers Chain
    |--------------------------------------------------------------------------
    |
    | A prioritised list of exchange rate providers. When syncing, the system
    | traverses this chain, failing over sequentially if a provider is
    | unavailable, times out, or returns a malformed payload.
    |
    */
    'providers' => [
        'primary' => 'open_exchange_rates',
        'failovers' => [
            'ecb',
            'mock',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting Fallbacks (intl Extension Missing)
    |--------------------------------------------------------------------------
    |
    | Used strictly as a fallback in environments where the PHP 'intl'
    | extension is not installed. Canonical formatting is driven by the locale
    | and PHP NumberFormatter directly.
    |
    */
    'formatting' => [
        'EGP' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_placement' => 'after',
        ],
        'USD' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_placement' => 'before',
        ],
        'EUR' => [
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_placement' => 'before',
        ],
        'BHD' => [
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_placement' => 'before',
        ],
    ],
];
