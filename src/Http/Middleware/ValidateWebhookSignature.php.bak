<?php

namespace Cashkdiopen\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Cashkdiopen\Laravel\Services\SignatureService;
use Cashkdiopen\Laravel\Exceptions\WebhookException;
use Illuminate\Support\Facades\Log;

class ValidateWebhookSignature
{
    protected SignatureService $signatureService;

    public function __construct(SignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            // Skip validation in testing environment if configured
            if (app()->environment('testing') && config('cashkdiopen.testing.skip_webhook_validation', false)) {
                return $next($request);
            }

            // Get webhook headers
            $signature = $request->header('X-Cashkdiopen-Signature');
            $timestamp = $request->header('X-Cashkdiopen-Timestamp');
            $payload = $request->getContent();

            // Validate required headers
            if (empty($signature)) {
                throw new WebhookException('Missing signature header');
            }

            if (empty($timestamp)) {
                throw new WebhookException('Missing timestamp header');
            }

            if (empty($payload)) {
                throw new WebhookException('Empty webhook payload');
            }

            // Validate timestamp (protect against replay attacks)
            $this->validateTimestamp($timestamp);

            // Validate signature
            $this->validateSignature($signature, $timestamp, $payload);

            // Log successful validation
            Log::debug('Webhook signature validated successfully', [
                'signature_present' => !empty($signature),
                'timestamp' => $timestamp,
                'payload_length' => strlen($payload),
                'remote_ip' => $request->ip(),
            ]);

            return $next($request);

        } catch (WebhookException $e) {
            Log::warning('Webhook signature validation failed', [
                'error' => $e->getMessage(),
                'signature_present' => !empty($signature ?? null),
                'timestamp_present' => !empty($timestamp ?? null),
                'payload_length' => strlen($payload ?? ''),
                'remote_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response('Webhook signature validation failed', 400);

        } catch (\Exception $e) {
            Log::error('Unexpected error during webhook validation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'remote_ip' => $request->ip(),
            ]);

            return response('Internal server error', 500);
        }
    }

    /**
     * Validate the webhook timestamp.
     */
    protected function validateTimestamp(string $timestamp): void
    {
        // Check if timestamp is numeric
        if (!is_numeric($timestamp)) {
            throw new WebhookException('Invalid timestamp format');
        }

        $timestampInt = (int) $timestamp;
        $currentTime = time();
        $tolerance = config('cashkdiopen.webhooks.timestamp_tolerance', 300); // 5 minutes default

        // Check if timestamp is too old
        if ($currentTime - $timestampInt > $tolerance) {
            throw new WebhookException('Webhook timestamp is too old');
        }

        // Check if timestamp is too far in the future (clock skew protection)
        if ($timestampInt - $currentTime > $tolerance) {
            throw new WebhookException('Webhook timestamp is too far in the future');
        }
    }

    /**
     * Validate the webhook signature.
     */
    protected function validateSignature(string $signature, string $timestamp, string $payload): void
    {
        // Determine provider from route or headers
        $provider = $this->getProviderFromRequest();
        
        // Get webhook secret for the provider
        $secret = $this->getWebhookSecret($provider);
        
        if (empty($secret)) {
            throw new WebhookException("Webhook secret not configured for provider: {$provider}");
        }

        // Validate signature using the signature service
        if (!$this->signatureService->validateWebhookSignature($payload, $signature, $timestamp, $secret)) {
            throw new WebhookException('Invalid webhook signature');
        }
    }

    /**
     * Get the provider from the current request.
     */
    protected function getProviderFromRequest(): string
    {
        $request = request();
        
        // Try to get provider from route parameter
        if ($request->route('provider')) {
            return $request->route('provider');
        }

        // Try to determine from route name
        $routeName = $request->route()->getName();
        if (str_contains($routeName, 'orange-money')) {
            return 'orange_money';
        }
        if (str_contains($routeName, 'mtn-momo')) {
            return 'mtn_momo';
        }
        if (str_contains($routeName, 'cards')) {
            return 'cards';
        }

        // Try to determine from URL path
        $path = $request->path();
        if (str_contains($path, 'orange-money')) {
            return 'orange_money';
        }
        if (str_contains($path, 'mtn-momo')) {
            return 'mtn_momo';
        }
        if (str_contains($path, 'cards')) {
            return 'cards';
        }

        // Default to generic webhook secret
        return 'default';
    }

    /**
     * Get the webhook secret for the provider.
     */
    protected function getWebhookSecret(string $provider): ?string
    {
        // Try provider-specific secret first
        $secret = config("cashkdiopen.providers.{$provider}.webhook_secret");
        
        if (empty($secret)) {
            // Fall back to global webhook secret
            $secret = config('cashkdiopen.webhooks.secret');
        }

        return $secret;
    }
}