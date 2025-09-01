<?php

namespace Cashkdiopen\Laravel\Tests\Feature;

use Cashkdiopen\Laravel\Tests\TestCase;
use Cashkdiopen\Laravel\Models\Transaction;
use Cashkdiopen\Laravel\Models\ApiKey;

class PaymentApiTest extends TestCase
{
    /** @test */
    public function it_requires_authentication_for_payment_creation()
    {
        $response = $this->postJson('/api/cashkdiopen/payments', [
            'amount' => 10000,
            'currency' => 'XOF',
            'method' => 'orange_money',
            'customer_phone' => '+22607123456',
            'description' => 'Test payment',
            'callback_url' => 'https://example.com/webhook',
            'return_url' => 'https://example.com/success',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'API key is required',
            ],
        ]);
    }

    /** @test */
    public function it_can_create_a_payment_with_valid_api_key()
    {
        $apiKey = $this->createTestApiKey();

        $response = $this->postJson('/api/cashkdiopen/payments', [
            'amount' => 10000,
            'currency' => 'XOF',
            'method' => 'orange_money',
            'customer_phone' => '+22607123456',
            'description' => 'Test payment',
            'callback_url' => 'https://example.com/webhook',
            'return_url' => 'https://example.com/success',
        ], $this->getAuthHeader($apiKey));

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'amount' => 10000,
                'currency' => 'XOF',
                'method' => 'orange_money',
                'status' => 'pending',
            ],
        ]);

        $this->assertDatabaseHas('cashkdiopen_transactions', [
            'amount' => 10000,
            'currency' => 'XOF',
            'provider' => 'orange_money',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_validates_payment_creation_data()
    {
        $apiKey = $this->createTestApiKey();

        $response = $this->postJson('/api/cashkdiopen/payments', [
            'amount' => 50, // Too low
            'currency' => 'INVALID',
            'method' => 'invalid_method',
            'description' => '', // Empty
            'callback_url' => 'not-a-url',
        ], $this->getAuthHeader($apiKey));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_FAILED',
            ],
        ]);

        $response->assertJsonValidationErrors([
            'amount',
            'currency',
            'method',
            'customer_phone', // Required for mobile money
            'description',
            'callback_url',
            'return_url',
        ]);
    }

    /** @test */
    public function it_requires_phone_for_mobile_money()
    {
        $apiKey = $this->createTestApiKey();

        $response = $this->postJson('/api/cashkdiopen/payments', [
            'amount' => 10000,
            'currency' => 'XOF',
            'method' => 'orange_money',
            'description' => 'Test payment',
            'callback_url' => 'https://example.com/webhook',
            'return_url' => 'https://example.com/success',
        ], $this->getAuthHeader($apiKey));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_phone']);
    }

    /** @test */
    public function it_can_retrieve_payment_details()
    {
        $apiKey = $this->createTestApiKey();
        $transaction = $this->createTestTransaction(['api_key_id' => $apiKey->id]);

        $response = $this->getJson(
            "/api/cashkdiopen/payments/{$transaction->reference}",
            $this->getAuthHeader($apiKey)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => 10000,
                'currency' => 'XOF',
                'status' => 'pending',
            ],
        ]);
    }

    /** @test */
    public function it_prevents_access_to_other_users_transactions()
    {
        $apiKey1 = $this->createTestApiKey();
        $apiKey2 = $this->createTestApiKey();
        $transaction = $this->createTestTransaction(['api_key_id' => $apiKey1->id]);

        $response = $this->getJson(
            "/api/cashkdiopen/payments/{$transaction->reference}",
            $this->getAuthHeader($apiKey2)
        );

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'TRANSACTION_ACCESS_DENIED',
            ],
        ]);
    }

    /** @test */
    public function it_can_get_payment_status()
    {
        $apiKey = $this->createTestApiKey();
        $transaction = $this->createTestTransaction(['api_key_id' => $apiKey->id]);

        $response = $this->getJson(
            "/api/cashkdiopen/payments/{$transaction->reference}/status",
            $this->getAuthHeader($apiKey)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'status' => 'pending',
            ],
        ]);
    }

    /** @test */
    public function it_can_list_payments_with_pagination()
    {
        $apiKey = $this->createTestApiKey();
        
        // Create multiple transactions
        $this->createTestTransaction(['api_key_id' => $apiKey->id]);
        $this->createTestTransaction(['api_key_id' => $apiKey->id, 'amount' => 20000]);
        $this->createTestTransaction(['api_key_id' => $apiKey->id, 'currency' => 'USD']);

        $response = $this->getJson(
            '/api/cashkdiopen/payments?per_page=2',
            $this->getAuthHeader($apiKey)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'meta' => [
                'current_page' => 1,
                'per_page' => 2,
                'total' => 3,
                'last_page' => 2,
            ],
        ]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_payments()
    {
        $apiKey = $this->createTestApiKey();
        
        $pendingTransaction = $this->createTestTransaction(['api_key_id' => $apiKey->id]);
        $successTransaction = $this->createTestTransaction(['api_key_id' => $apiKey->id]);
        $successTransaction->markAsSuccessful();

        $response = $this->getJson(
            '/api/cashkdiopen/payments?status=success',
            $this->getAuthHeader($apiKey)
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($successTransaction->id, $response->json('data.0.id'));
    }

    /** @test */
    public function it_returns_404_for_nonexistent_payment()
    {
        $apiKey = $this->createTestApiKey();

        $response = $this->getJson(
            '/api/cashkdiopen/payments/nonexistent',
            $this->getAuthHeader($apiKey)
        );

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'TRANSACTION_NOT_FOUND',
            ],
        ]);
    }

    /** @test */
    public function it_includes_rate_limit_headers()
    {
        $apiKey = $this->createTestApiKey();

        $response = $this->getJson('/api/cashkdiopen/payments', $this->getAuthHeader($apiKey));

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
    }
}