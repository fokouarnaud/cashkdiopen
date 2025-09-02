<?php

namespace Cashkdiopen\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RateLimitByApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $rateLimitKey = null): mixed
    {
        try {
            // Skip rate limiting if disabled in config
            if (!config('cashkdiopen.rate_limiting.enabled', true)) {
                return $next($request);
            }

            // Get API key from request
            $apiKey = $request->apiKey ?? $request->attributes->get('api_key');
            
            if (!$apiKey) {
                // If no API key, apply default rate limiting by IP
                return $this->handleIpRateLimit($request, $next);
            }

            // Determine rate limit for this API key and endpoint
            $rateLimit = $this->getRateLimit($apiKey, $rateLimitKey);
            $rateLimitKey = $this->getRateLimitKey($apiKey, $rateLimitKey);

            // Check if rate limit exceeded
            if (RateLimiter::tooManyAttempts($rateLimitKey, $rateLimit['max_attempts'])) {
                return $this->rateLimitExceededResponse(
                    $rateLimitKey,
                    $rateLimit,
                    $apiKey->key_id
                );
            }

            // Record the attempt
            RateLimiter::hit($rateLimitKey, $rateLimit['window_seconds']);

            // Add rate limit headers to response
            $response = $next($request);
            
            return $this->addRateLimitHeaders(
                $response,
                $rateLimitKey,
                $rateLimit
            );

        } catch (\Exception $e) {
            Log::error('Error in rate limiting middleware', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'api_key_id' => $apiKey->key_id ?? 'unknown',
            ]);

            // Continue processing if rate limiting fails
            return $next($request);
        }
    }

    /**
     * Handle rate limiting for requests without API key (by IP).
     */
    protected function handleIpRateLimit(Request $request, Closure $next): mixed
    {
        $ip = $request->ip();
        $rateLimitKey = "ip_rate_limit:{$ip}";
        $maxAttempts = 60; // 60 requests per hour for unauthenticated requests
        $windowSeconds = 3600; // 1 hour

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->rateLimitExceededResponse($rateLimitKey, [
                'max_attempts' => $maxAttempts,
                'window_seconds' => $windowSeconds,
            ]);
        }

        RateLimiter::hit($rateLimitKey, $windowSeconds);

        return $next($request);
    }

    /**
     * Get rate limit configuration for API key and endpoint.
     */
    protected function getRateLimit($apiKey, ?string $rateLimitKey): array
    {
        // Use API key's custom rate limit if set
        $maxAttempts = $apiKey->rate_limit ?? config('cashkdiopen.rate_limiting.limits.api_requests', 1000);

        // Adjust limits based on endpoint type
        if ($rateLimitKey === 'payments:create') {
            $maxAttempts = min($maxAttempts, config('cashkdiopen.rate_limiting.limits.payment_creation', 100));
        } elseif ($rateLimitKey === 'payments:status') {
            $maxAttempts = min($maxAttempts, config('cashkdiopen.rate_limiting.limits.status_checks', 300));
        }

        // Production keys get higher limits
        if ($apiKey->isProduction()) {
            $maxAttempts = (int) ($maxAttempts * 1.5);
        }

        return [
            'max_attempts' => $maxAttempts,
            'window_seconds' => 3600, // 1 hour window
        ];
    }

    /**
     * Generate rate limit key for API key and endpoint.
     */
    protected function getRateLimitKey($apiKey, ?string $rateLimitKey): string
    {
        $baseKey = "api_rate_limit:{$apiKey->key_id}";
        
        if ($rateLimitKey) {
            $baseKey .= ":{$rateLimitKey}";
        }

        return $baseKey;
    }

    /**
     * Return rate limit exceeded response.
     */
    protected function rateLimitExceededResponse(
        string $rateLimitKey,
        array $rateLimit,
        ?string $apiKeyId = null
    ): Response {
        $availableAt = RateLimiter::availableAt($rateLimitKey);
        $retryAfter = $availableAt - time();

        Log::warning('Rate limit exceeded', [
            'rate_limit_key' => $rateLimitKey,
            'api_key_id' => $apiKeyId,
            'max_attempts' => $rateLimit['max_attempts'],
            'window_seconds' => $rateLimit['window_seconds'],
            'retry_after' => $retryAfter,
        ]);

        $response = response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please try again later.',
                'details' => [
                    'limit' => $rateLimit['max_attempts'],
                    'window_seconds' => $rateLimit['window_seconds'],
                    'retry_after' => $retryAfter,
                    'reset_at' => Carbon::createFromTimestamp($availableAt)->toISOString(),
                ]
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID') ?? 'req_' . \Illuminate\Support\Str::random(12),
                'timestamp' => now()->toISOString(),
            ],
        ], 429);

        // Add standard rate limit headers
        return $response->withHeaders([
            'X-RateLimit-Limit' => $rateLimit['max_attempts'],
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => $availableAt,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addRateLimitHeaders($response, string $rateLimitKey, array $rateLimit)
    {
        $remaining = RateLimiter::remaining($rateLimitKey, $rateLimit['max_attempts']);
        $availableAt = RateLimiter::availableAt($rateLimitKey);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $rateLimit['max_attempts'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $availableAt,
        ]);
    }
}