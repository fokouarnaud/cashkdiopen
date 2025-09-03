<?php

namespace Cashkdiopen\Payments\Http\Middleware;

use Cashkdiopen\Payments\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        $apiKeyModel = ApiKey::verify($apiKey);

        if (!$apiKeyModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired API key',
            ], 401);
        }

        // Check permissions if route requires specific permission
        $permission = $request->route()->getAction('permission');
        if ($permission && !$apiKeyModel->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
            ], 403);
        }

        // Record API key usage
        $apiKeyModel->recordUsage();

        // Add API key to request for later use
        $request->attributes->set('api_key', $apiKeyModel);

        return $next($request);
    }

    /**
     * Extract API key from request.
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Check Authorization header first
        $authorization = $request->header('Authorization');
        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        // Check X-API-Key header
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // Check query parameter as fallback (not recommended for production)
        return $request->query('api_key');
    }
}