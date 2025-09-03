<?php

namespace Cashkdiopen\Payments\Services;

use Illuminate\Http\Request;

class SignatureService
{
    public function __construct(
        protected array $config
    ) {}

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $provider, Request $request): bool
    {
        $providerConfig = $this->config['providers'][$provider] ?? [];
        
        if (empty($providerConfig['webhook_secret'])) {
            throw new \Exception("Webhook secret not configured for provider: {$provider}");
        }

        $signature = $this->extractSignature($provider, $request);
        $payload = $request->getContent();
        
        $expectedSignature = $this->generateSignature($provider, $payload, $providerConfig['webhook_secret']);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }

        return true;
    }

    /**
     * Extract signature from request based on provider.
     */
    protected function extractSignature(string $provider, Request $request): string
    {
        return match ($provider) {
            'orange-money' => $request->header('X-Orange-Signature', ''),
            'mtn-momo' => $request->header('X-MTN-Signature', ''),
            'cards' => $request->header('X-Webhook-Signature', ''),
            default => $request->header('X-Signature', ''),
        };
    }

    /**
     * Generate expected signature based on provider.
     */
    protected function generateSignature(string $provider, string $payload, string $secret): string
    {
        return match ($provider) {
            'orange-money' => $this->generateOrangeMoneySignature($payload, $secret),
            'mtn-momo' => $this->generateMtnMoMoSignature($payload, $secret),
            'cards' => $this->generateCardsSignature($payload, $secret),
            default => $this->generateGenericSignature($payload, $secret),
        };
    }

    /**
     * Generate Orange Money signature.
     */
    protected function generateOrangeMoneySignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Generate MTN Mobile Money signature.
     */
    protected function generateMtnMoMoSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Generate Cards signature.
     */
    protected function generateCardsSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Generate generic signature.
     */
    protected function generateGenericSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Sign API request.
     */
    public function signApiRequest(string $provider, string $method, string $url, string $payload, array $headers = []): string
    {
        $providerConfig = $this->config['providers'][$provider] ?? [];
        $secret = $providerConfig['api_secret'] ?? '';

        if (empty($secret)) {
            throw new \Exception("API secret not configured for provider: {$provider}");
        }

        $stringToSign = strtoupper($method) . "\n" . 
                       parse_url($url, PHP_URL_PATH) . "\n" . 
                       $payload;

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    /**
     * Verify API response signature.
     */
    public function verifyApiResponseSignature(string $provider, string $payload, string $signature): bool
    {
        $providerConfig = $this->config['providers'][$provider] ?? [];
        $secret = $providerConfig['api_secret'] ?? '';

        if (empty($secret)) {
            return true; // Skip verification if no secret configured
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}