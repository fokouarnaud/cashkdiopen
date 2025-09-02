<?php

namespace Cashkdiopen\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Cashkdiopen\Laravel\CashkdiopenServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashkdiopenServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set test configuration
        config()->set('cashkdiopen.api.key', 'ck_test_1234567890123456789012345678901234567890');
        config()->set('cashkdiopen.api.environment', 'sandbox');
        config()->set('cashkdiopen.webhooks.secret', 'test_webhook_secret');
        config()->set('cashkdiopen.testing.fake_providers', true);
    }

    /**
     * Create a test API key.
     */
    protected function createTestApiKey(array $attributes = []): \Cashkdiopen\Laravel\Models\ApiKey
    {
        return \Cashkdiopen\Laravel\Models\ApiKey::create(array_merge([
            'name' => 'Test API Key',
            'environment' => 'sandbox',
            'scopes' => ['payments:create', 'payments:read'],
        ], $attributes));
    }

    /**
     * Create a test transaction.
     */
    protected function createTestTransaction(array $attributes = []): \Cashkdiopen\Laravel\Models\Transaction
    {
        $apiKey = $this->createTestApiKey();
        
        return \Cashkdiopen\Laravel\Models\Transaction::create(array_merge([
            'provider' => 'orange_money',
            'amount' => 10000, // 100.00 in cents
            'currency' => 'XOF',
            'customer_phone' => '+22607123456',
            'description' => 'Test payment',
            'callback_url' => 'https://example.com/webhook',
            'return_url' => 'https://example.com/success',
            'api_key_id' => $apiKey->id,
        ], $attributes));
    }

    /**
     * Get authorization header for API key.
     */
    protected function getAuthHeader(\Cashkdiopen\Laravel\Models\ApiKey $apiKey): array
    {
        return ['Authorization' => 'Bearer ' . $apiKey->key_id];
    }
}