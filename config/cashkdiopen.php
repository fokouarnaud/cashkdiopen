<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    |
    | This option determines the default payment provider that will be used
    | when no specific provider is requested. You may change this to any
    | of the supported providers: orange_money, mtn_momo, cards
    |
    */
    
    'default_provider' => env('CASHKDIOPEN_DEFAULT_PROVIDER', 'orange_money'),
    
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Cashkdiopen API settings including your
    | API key, environment (sandbox/production), and other global settings.
    |
    */
    
    'api' => [
        'key' => env('CASHKDIOPEN_API_KEY'),
        'environment' => env('CASHKDIOPEN_ENVIRONMENT', 'sandbox'),
        'base_url' => env('CASHKDIOPEN_BASE_URL', 'https://api.cashkdiopen.com/v1'),
        'timeout' => env('CASHKDIOPEN_TIMEOUT', 30),
        'retry_attempts' => env('CASHKDIOPEN_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('CASHKDIOPEN_RETRY_DELAY', 1000), // milliseconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Payment Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure each payment provider with their specific
    | settings, credentials, and endpoints. Each provider may have different
    | configuration requirements.
    |
    */
    
    'providers' => [
        'orange_money' => [
            'enabled' => env('ORANGE_MONEY_ENABLED', true),
            'base_url' => env('ORANGE_MONEY_BASE_URL', 'https://api.orange.money'),
            'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
            'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
            'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
            'timeout' => env('ORANGE_MONEY_TIMEOUT', 30),
            'countries' => ['CI', 'SN', 'ML', 'BF', 'NE', 'GN', 'CM'],
            'currencies' => ['XOF', 'XAF'],
            'min_amount' => 100, // XOF/XAF cents
            'max_amount' => 500000000, // XOF/XAF cents (5M)
        ],
        
        'mtn_momo' => [
            'enabled' => env('MTN_MOMO_ENABLED', true),
            'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'api_user' => env('MTN_MOMO_API_USER'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
            'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
            'timeout' => env('MTN_MOMO_TIMEOUT', 30),
            'countries' => ['CI', 'CM', 'GH', 'UG', 'ZM'],
            'currencies' => ['XOF', 'XAF', 'GHS', 'UGX', 'ZMW'],
            'min_amount' => 100,
            'max_amount' => 500000000,
        ],
        
        'cards' => [
            'enabled' => env('CARDS_ENABLED', true),
            'base_url' => env('CARDS_BASE_URL', 'https://api.paygate.africa'),
            'merchant_id' => env('CARDS_MERCHANT_ID'),
            'secret_key' => env('CARDS_SECRET_KEY'),
            'webhook_secret' => env('CARDS_WEBHOOK_SECRET'),
            'timeout' => env('CARDS_TIMEOUT', 30),
            'supported_cards' => ['visa', 'mastercard', 'amex'],
            'currencies' => ['XOF', 'XAF', 'USD', 'EUR'],
            'min_amount' => 100,
            'max_amount' => 1000000000,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook processing including signature validation,
    | retry logic, and security settings.
    |
    */
    
    'webhooks' => [
        'signature_header' => 'X-Cashkdiopen-Signature',
        'timestamp_header' => 'X-Cashkdiopen-Timestamp',
        'event_header' => 'X-Cashkdiopen-Event',
        
        // Time tolerance for webhook timestamps (seconds)
        'timestamp_tolerance' => env('CASHKDIOPEN_WEBHOOK_TOLERANCE', 300), // 5 minutes
        
        // Webhook retry configuration
        'retry_attempts' => 3,
        'retry_delays' => [1, 5, 15], // minutes between retries
        
        // Route configuration
        'route_prefix' => 'webhooks',
        'route_middleware' => ['api'],
        
        // Security settings
        'verify_ssl' => env('CASHKDIOPEN_WEBHOOK_VERIFY_SSL', true),
        'allowed_ips' => [], // Empty array allows all IPs
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database table names and connection settings for Cashkdiopen.
    |
    */
    
    'database' => [
        'connection' => env('CASHKDIOPEN_DB_CONNECTION', null),
        
        'tables' => [
            'transactions' => 'cashkdiopen_transactions',
            'payments' => 'cashkdiopen_payments',
            'api_keys' => 'cashkdiopen_api_keys',
            'webhook_logs' => 'cashkdiopen_webhook_logs',
        ],
        
        // Automatic cleanup settings
        'cleanup' => [
            'enabled' => env('CASHKDIOPEN_AUTO_CLEANUP', false),
            'retain_days' => env('CASHKDIOPEN_RETAIN_DAYS', 90),
            'cleanup_schedule' => 'daily', // or custom cron expression
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache settings for payment status, provider tokens, and other data.
    |
    */
    
    'cache' => [
        'store' => env('CASHKDIOPEN_CACHE_STORE', 'default'),
        'prefix' => env('CASHKDIOPEN_CACHE_PREFIX', 'cashkdiopen'),
        
        'ttl' => [
            'payment_status' => env('CASHKDIOPEN_CACHE_PAYMENT_STATUS', 300), // 5 minutes
            'provider_token' => env('CASHKDIOPEN_CACHE_PROVIDER_TOKEN', 3500), // 58 minutes
            'exchange_rates' => env('CASHKDIOPEN_CACHE_EXCHANGE_RATES', 3600), // 1 hour
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for processing webhooks, status checks, and other
    | asynchronous operations.
    |
    */
    
    'queue' => [
        'default' => env('CASHKDIOPEN_QUEUE_CONNECTION', 'default'),
        
        'queues' => [
            'webhooks' => env('CASHKDIOPEN_WEBHOOK_QUEUE', 'cashkdiopen-webhooks'),
            'status_checks' => env('CASHKDIOPEN_STATUS_QUEUE', 'cashkdiopen-status'),
            'notifications' => env('CASHKDIOPEN_NOTIFICATION_QUEUE', 'cashkdiopen-notifications'),
        ],
        
        'job_retries' => env('CASHKDIOPEN_JOB_RETRIES', 3),
        'job_timeout' => env('CASHKDIOPEN_JOB_TIMEOUT', 60), // seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Logging settings including channels, levels, and data sanitization.
    |
    */
    
    'logging' => [
        'channel' => env('CASHKDIOPEN_LOG_CHANNEL', 'stack'),
        'level' => env('CASHKDIOPEN_LOG_LEVEL', 'info'),
        
        // Sensitive fields that should be masked in logs
        'sensitive_fields' => [
            'client_secret',
            'api_key',
            'webhook_secret',
            'customer_phone', // Will be partially masked
            'card_number',
            'cvv',
        ],
        
        // Log request/response data
        'log_requests' => env('CASHKDIOPEN_LOG_REQUESTS', true),
        'log_responses' => env('CASHKDIOPEN_LOG_RESPONSES', true),
        
        // Performance logging
        'log_performance' => env('CASHKDIOPEN_LOG_PERFORMANCE', true),
        'slow_query_threshold' => env('CASHKDIOPEN_SLOW_QUERY_THRESHOLD', 1000), // ms
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration to prevent API abuse and ensure fair usage.
    |
    */
    
    'rate_limiting' => [
        'enabled' => env('CASHKDIOPEN_RATE_LIMITING', true),
        
        'limits' => [
            // Requests per minute per API key
            'api_requests' => env('CASHKDIOPEN_API_RATE_LIMIT', 60),
            
            // Payment creation limit per minute per API key
            'payment_creation' => env('CASHKDIOPEN_PAYMENT_RATE_LIMIT', 20),
            
            // Status check limit per minute per API key
            'status_checks' => env('CASHKDIOPEN_STATUS_RATE_LIMIT', 100),
        ],
        
        'store' => env('CASHKDIOPEN_RATE_LIMIT_STORE', 'redis'),
        'key_generator' => null, // Custom key generator class
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security configuration including encryption, IP restrictions, etc.
    |
    */
    
    'security' => [
        // Encrypt sensitive data in database
        'encrypt_sensitive_data' => env('CASHKDIOPEN_ENCRYPT_SENSITIVE', true),
        
        // IP whitelist for API access (empty array allows all)
        'allowed_ips' => [],
        
        // Force HTTPS in production
        'force_https' => env('CASHKDIOPEN_FORCE_HTTPS', true),
        
        // API key rotation
        'api_key_rotation' => [
            'enabled' => env('CASHKDIOPEN_KEY_ROTATION', false),
            'rotation_days' => env('CASHKDIOPEN_KEY_ROTATION_DAYS', 90),
            'warning_days' => env('CASHKDIOPEN_KEY_WARNING_DAYS', 7),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */
    
    'testing' => [
        // Use fake providers in testing
        'fake_providers' => env('CASHKDIOPEN_FAKE_PROVIDERS', false),
        
        // Test payment amounts that always succeed/fail
        'test_amounts' => [
            'success' => [1000, 2000, 5000], // These amounts always succeed in sandbox
            'failure' => [9999], // These amounts always fail in sandbox
        ],
        
        // Mock webhook delays for testing
        'mock_webhook_delay' => env('CASHKDIOPEN_MOCK_WEBHOOK_DELAY', 0), // seconds
    ],
];