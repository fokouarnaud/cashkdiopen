<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    |
    | The default payment provider to use when none is specified.
    */
    'default_provider' => env('CASHKDIOPEN_DEFAULT_PROVIDER', 'orange-money'),

    /*
    |--------------------------------------------------------------------------
    | Payment Timeout
    |--------------------------------------------------------------------------
    |
    | Default timeout in minutes for payments before they expire.
    */
    'payment_timeout' => env('CASHKDIOPEN_PAYMENT_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each payment provider.
    */
    'providers' => [
        'orange-money' => [
            'api_url' => env('ORANGE_MONEY_API_URL', 'https://api.orange.com'),
            'api_key' => env('ORANGE_MONEY_API_KEY'),
            'api_secret' => env('ORANGE_MONEY_API_SECRET'),
            'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
            'test_mode' => env('ORANGE_MONEY_TEST_MODE', true),
        ],

        'mtn-momo' => [
            'api_url' => env('MTN_MOMO_API_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'api_secret' => env('MTN_MOMO_API_SECRET'),
            'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
            'test_mode' => env('MTN_MOMO_TEST_MODE', true),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
        ],

        'cards' => [
            'api_url' => env('CARDS_API_URL', 'https://api.example.com'),
            'api_key' => env('CARDS_API_KEY'),
            'api_secret' => env('CARDS_API_SECRET'),
            'webhook_secret' => env('CARDS_WEBHOOK_SECRET'),
            'test_mode' => env('CARDS_TEST_MODE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for handling webhooks from payment providers.
    */
    'webhooks' => [
        'verify_signatures' => env('CASHKDIOPEN_VERIFY_SIGNATURES', true),
        'route_prefix' => env('CASHKDIOPEN_WEBHOOK_PREFIX', 'webhooks'),
        'route_middleware' => ['api'],
        'max_retry_attempts' => env('CASHKDIOPEN_WEBHOOK_RETRY_ATTEMPTS', 5),
        'retry_delay_minutes' => env('CASHKDIOPEN_WEBHOOK_RETRY_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Cashkdiopen API.
    */
    'api' => [
        'rate_limit' => env('CASHKDIOPEN_RATE_LIMIT', 1000),
        'rate_limit_per' => env('CASHKDIOPEN_RATE_LIMIT_PER', 'hour'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for payment operations.
    */
    'logging' => [
        'enabled' => env('CASHKDIOPEN_LOGGING_ENABLED', true),
        'channel' => env('CASHKDIOPEN_LOG_CHANNEL', 'daily'),
        'level' => env('CASHKDIOPEN_LOG_LEVEL', 'info'),
    ],
];