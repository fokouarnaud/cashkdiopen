<?php

namespace Cashkdiopen\Laravel\Tests\Unit\Models;

use Cashkdiopen\Laravel\Tests\TestCase;
use Cashkdiopen\Laravel\Models\Transaction;
use Cashkdiopen\Laravel\Models\ApiKey;

class TransactionTest extends TestCase
{
    /** @test */
    public function it_can_create_a_transaction()
    {
        $apiKey = $this->createTestApiKey();
        
        $transaction = Transaction::create([
            'provider' => 'orange_money',
            'amount' => 10000,
            'currency' => 'XOF',
            'customer_phone' => '+22607123456',
            'description' => 'Test payment',
            'callback_url' => 'https://example.com/webhook',
            'return_url' => 'https://example.com/success',
            'api_key_id' => $apiKey->id,
        ]);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertNotEmpty($transaction->id);
        $this->assertNotEmpty($transaction->reference);
        $this->assertEquals('pending', $transaction->status);
        $this->assertEquals('orange_money', $transaction->provider);
        $this->assertEquals(10000, $transaction->amount);
    }

    /** @test */
    public function it_generates_unique_reference()
    {
        $transaction1 = $this->createTestTransaction();
        $transaction2 = $this->createTestTransaction();

        $this->assertNotEquals($transaction1->reference, $transaction2->reference);
        $this->assertStringStartsWith('CK_', $transaction1->reference);
        $this->assertStringStartsWith('CK_', $transaction2->reference);
    }

    /** @test */
    public function it_can_mark_as_successful()
    {
        $transaction = $this->createTestTransaction();

        $transaction->markAsSuccessful('provider_ref_123');

        $this->assertEquals('success', $transaction->status);
        $this->assertEquals('provider_ref_123', $transaction->provider_reference);
        $this->assertNotNull($transaction->completed_at);
        $this->assertTrue($transaction->isSuccessful());
        $this->assertTrue($transaction->isFinal());
    }

    /** @test */
    public function it_can_mark_as_failed()
    {
        $transaction = $this->createTestTransaction();

        $transaction->markAsFailed('Insufficient funds');

        $this->assertEquals('failed', $transaction->status);
        $this->assertNotNull($transaction->completed_at);
        $this->assertTrue($transaction->hasFailed());
        $this->assertTrue($transaction->isFinal());
        $this->assertEquals('Insufficient funds', $transaction->metadata['failure_reason']);
    }

    /** @test */
    public function it_prevents_status_changes_on_final_transactions()
    {
        $transaction = $this->createTestTransaction();
        $transaction->markAsSuccessful();

        $this->expectException(\RuntimeException::class);
        $transaction->markAsFailed();
    }

    /** @test */
    public function it_can_check_expiry()
    {
        // Create expired transaction
        $expiredTransaction = $this->createTestTransaction([
            'expires_at' => now()->subMinutes(10),
        ]);

        // Create valid transaction
        $validTransaction = $this->createTestTransaction([
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertTrue($expiredTransaction->isExpired());
        $this->assertFalse($validTransaction->isExpired());
    }

    /** @test */
    public function it_can_check_if_cancelable()
    {
        $pendingTransaction = $this->createTestTransaction();
        $successfulTransaction = $this->createTestTransaction();
        $expiredTransaction = $this->createTestTransaction(['expires_at' => now()->subMinutes(10)]);

        $successfulTransaction->markAsSuccessful();

        $this->assertTrue($pendingTransaction->canBeCanceled());
        $this->assertFalse($successfulTransaction->canBeCanceled());
        $this->assertFalse($expiredTransaction->canBeCanceled());
    }

    /** @test */
    public function it_masks_phone_number()
    {
        $transaction = $this->createTestTransaction([
            'customer_phone' => '+22607123456',
        ]);

        $maskedPhone = $transaction->masked_phone;
        $this->assertEquals('+226****56', $maskedPhone);
    }

    /** @test */
    public function it_formats_amount_correctly()
    {
        $transaction = $this->createTestTransaction(['amount' => 10000]);

        // Test getter (converts cents to major unit)
        $this->assertEquals(100.0, $transaction->amount);
        $this->assertEquals('100.00 XOF', $transaction->formatted_amount);
    }

    /** @test */
    public function it_has_relationships()
    {
        $transaction = $this->createTestTransaction();

        $this->assertInstanceOf(ApiKey::class, $transaction->apiKey);
    }

    /** @test */
    public function it_has_proper_scopes()
    {
        $successfulTransaction = $this->createTestTransaction();
        $failedTransaction = $this->createTestTransaction();
        $pendingTransaction = $this->createTestTransaction();

        $successfulTransaction->markAsSuccessful();
        $failedTransaction->markAsFailed();

        $successfulTransactions = Transaction::successful()->get();
        $failedTransactions = Transaction::failed()->get();
        $pendingTransactions = Transaction::pending()->get();

        $this->assertTrue($successfulTransactions->contains($successfulTransaction));
        $this->assertTrue($failedTransactions->contains($failedTransaction));
        $this->assertTrue($pendingTransactions->contains($pendingTransaction));
    }
}