<?php

namespace Cashkdiopen\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Cashkdiopen\Laravel\Models\ApiKey;
use Illuminate\Support\Facades\Log;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            // Extract API key from request
            $apiKeyString = $this->extractApiKey($request);

            if (empty($apiKeyString)) {
                return $this->unauthorizedResponse('API key is required');
            }

            // Find and validate API key
            $apiKey = $this->findApiKey($apiKeyString);

            if (!$apiKey) {
                Log::warning('Invalid API key used', [
                    'key_preview' => substr($apiKeyString, 0, 10) . '...',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return $this->unauthorizedResponse('Invalid API key');
            }

            // Check if API key is active
            if (!$apiKey->isActive()) {
                Log::warning('Inactive API key used', [
                    'key_id' => $apiKey->key_id,
                    'user_id' => $apiKey->user_id,
                    'expired' => $apiKey->isExpired(),
                    'deleted' => $apiKey->trashed(),
                ]);

                return $this->unauthorizedResponse('API key is inactive or expired');
            }

            // Update last used timestamp
            $apiKey->updateLastUsed();

            // Store API key in request for later use
            $request->apiKey = $apiKey;
            $request->attributes->set('api_key', $apiKey);

            Log::debug('API key authenticated successfully', [
                'key_id' => $apiKey->key_id,
                'user_id' => $apiKey->user_id,
                'environment' => $apiKey->environment,
                'scopes' => $apiKey->scopes,
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Error during API key authentication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_ERROR',
                    'message' => 'Authentication failed',
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-ID') ?? 'req_' . \Illuminate\Support\Str::random(12),
                    'timestamp' => now()->toISOString(),
                ],
            ], 500);
        }
    }

    /**
     * Extract API key from request.
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Try Authorization header first (Bearer token)
        $authorization = $request->header('Authorization');
        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        // Try X-API-Key header
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // Try query parameter (not recommended for production)
        if (app()->environment('local', 'testing')) {
            $apiKey = $request->query('api_key');
            if ($apiKey) {
                return $apiKey;
            }
        }

        return null;
    }

    /**
     * Find API key in database.
     */
    protected function findApiKey(string $apiKeyString): ?ApiKey
    {
        // Basic validation of API key format
        if (!$this->isValidApiKeyFormat($apiKeyString)) {
            return null;
        }

        // Find by key_id (which is what we expect to receive)
        return ApiKey::where('key_id', $apiKeyString)
                     ->whereNull('deleted_at')
                     ->first();
    }

    /**
     * Validate API key format.
     */
    protected function isValidApiKeyFormat(string $apiKey): bool
    {
        // Check if it matches the expected pattern: ck_(test|live)_[32 chars]
        return preg_match('/^ck_(test|live)_[a-z0-9]{32}$/', $apiKey) === 1;
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID') ?? 'req_' . \Illuminate\Support\Str::random(12),
                'timestamp' => now()->toISOString(),
            ],
        ], 401);
    }
}