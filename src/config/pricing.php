<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pricing default currency and allowed currencies
    |--------------------------------------------------------------------------
    |
    | The default currency used when no currency is specified on a variant price.
    | Currencies are defined by their ISO 4217 three-letter code.
    |
    | The exponent for each currency determines how many minor units make up one
    | major unit. For example, USD has exponent 2 (12.34 USD = 1234 minor units),
    | while JPY has exponent 0 (1200 JPY = 1200 minor units - no decimal places).
    |
    */

    'default_currency' => env('PRICING_DEFAULT_CURRENCY', 'NGN'),

    'allowed_currencies' => [

        'NGN' => 2,
        'USD' => 2,
        'EUR' => 2,
        'GBP' => 2,

    ],

    /*
    |--------------------------------------------------------------------------
    | Currency exponent map
    |--------------------------------------------------------------------------
    |
    | This map is the single source of truth for parsing and formatting monetary
    | values. The exponent indicates the number of decimal places for the
    | currency. An exponent of 0 means whole units (e.g. JPY), an exponent of 2
    | means two decimal places (e.g. USD), and an exponent of 3 means three
    | decimal places (e.g. BIF).
    |
    | Values must be integers >= 0. Unknown currencies are rejected at the
    | application level.
    |
    */

    'exponents' => [

        'NGN' => 2,
        'USD' => 2,
        'EUR' => 2,
        'GBP' => 2,

    ],

];
