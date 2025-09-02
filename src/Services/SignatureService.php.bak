<?php

namespace Cashkdiopen\Laravel\Services;

use Cashkdiopen\Laravel\Exceptions\WebhookException;

class SignatureService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Validate webhook signature using HMAC SHA-256.
     */
    public function validateWebhookSignature(
        string $payload,
        string $receivedSignature,
        string $timestamp,
        string $secret
    ): bool {
        // Remove 'sha256=' prefix if present
        $receivedSignature = str_replace('sha256=', '', $receivedSignature);

        // Create expected signature
        $expectedSignature = $this->generateSignature($payload, $timestamp, $secret);

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Generate HMAC signature for webhook.
     */
    public function generateSignature(string $payload, string $timestamp, string $secret): string
    {
        $stringToSign = $timestamp . $payload;
        return hash_hmac('sha256', $stringToSign, $secret);
    }

    /**
     * Generate webhook signature with timestamp.
     */
    public function generateWebhookSignature(string $payload, string $secret): array
    {
        $timestamp = (string) time();
        $signature = $this->generateSignature($payload, $timestamp, $secret);

        return [
            'signature' => 'sha256=' . $signature,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Validate API request signature (for future use).
     */
    public function validateApiSignature(
        string $method,
        string $uri,
        string $body,
        string $timestamp,
        string $receivedSignature,
        string $apiSecret
    ): bool {
        $stringToSign = $method . "\n" . $uri . "\n" . $body . "\n" . $timestamp;
        $expectedSignature = hash_hmac('sha256', $stringToSign, $apiSecret);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Generate nonce for request signing.
     */
    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Verify timestamp is within acceptable range.
     */
    public function isTimestampValid(string $timestamp, int $tolerance = 300): bool
    {
        if (!is_numeric($timestamp)) {
            return false;
        }

        $timestampInt = (int) $timestamp;
        $currentTime = time();

        // Check if timestamp is too old or too far in the future
        return abs($currentTime - $timestampInt) <= $tolerance;
    }
}