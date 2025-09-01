<?php

namespace Cashkdiopen\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Skip logging in testing environment if configured
        if (app()->environment('testing') && !config('cashkdiopen.logging.log_in_testing', false)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $requestId = $this->getOrGenerateRequestId($request);
        
        // Store request ID in request attributes for other middleware/controllers
        $request->attributes->set('request_id', $requestId);
        
        // Log incoming request
        $this->logIncomingRequest($request, $requestId);
        
        // Process request
        $response = $next($request);
        
        // Log outgoing response
        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $this->logOutgoingResponse($request, $response, $requestId, $processingTime);
        
        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);
        
        return $response;
    }

    /**
     * Get existing request ID or generate a new one.
     */
    protected function getOrGenerateRequestId(Request $request): string
    {
        return $request->header('X-Request-ID') 
            ?? $request->header('X-Correlation-ID') 
            ?? 'req_' . Str::random(12);
    }

    /**
     * Log incoming request details.
     */
    protected function logIncomingRequest(Request $request, string $requestId): void
    {
        if (!config('cashkdiopen.logging.log_requests', true)) {
            return;
        }

        $apiKey = $request->apiKey ?? $request->attributes->get('api_key');
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'api_key_id' => $apiKey?->key_id,
            'api_key_environment' => $apiKey?->environment,
        ];

        // Add query parameters if present
        if ($request->query()) {
            $logData['query_params'] = $this->sanitizeData($request->query());
        }

        // Add request body for write operations (POST, PUT, PATCH)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && $request->getContent()) {
            $contentType = $request->header('Content-Type', '');
            
            if (str_contains($contentType, 'application/json')) {
                $body = json_decode($request->getContent(), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $logData['request_body'] = $this->sanitizeData($body);
                } else {
                    $logData['request_body'] = '[Invalid JSON]';
                }
            } else {
                $logData['request_body_size'] = strlen($request->getContent());
                $logData['content_type'] = $contentType;
            }
        }

        // Add important headers (excluding sensitive ones)
        $importantHeaders = [
            'Content-Type',
            'Accept',
            'X-Requested-With',
            'Origin',
            'Referer'
        ];
        
        $headers = [];
        foreach ($importantHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $request->header($header);
            }
        }
        
        if (!empty($headers)) {
            $logData['headers'] = $headers;
        }

        Log::info('API request received', $logData);
    }

    /**
     * Log outgoing response details.
     */
    protected function logOutgoingResponse(Request $request, $response, string $requestId, float $processingTime): void
    {
        if (!config('cashkdiopen.logging.log_responses', true)) {
            return;
        }

        $apiKey = $request->apiKey ?? $request->attributes->get('api_key');
        $statusCode = $response->getStatusCode();
        
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'processing_time_ms' => round($processingTime, 2),
            'api_key_id' => $apiKey?->key_id,
        ];

        // Add response size
        $content = $response->getContent();
        if ($content) {
            $logData['response_size_bytes'] = strlen($content);
        }

        // Log response body for errors (4xx, 5xx) or if configured to log all responses
        if ($statusCode >= 400 || config('cashkdiopen.logging.log_all_response_bodies', false)) {
            if ($content && $this->isJsonResponse($response)) {
                $responseData = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $logData['response_body'] = $this->sanitizeData($responseData);
                }
            }
        }

        // Determine log level based on status code
        $logLevel = $this->getLogLevelForStatus($statusCode);
        
        // Add additional context for errors
        if ($statusCode >= 500) {
            $logData['error_type'] = 'server_error';
        } elseif ($statusCode >= 400) {
            $logData['error_type'] = 'client_error';
        }

        // Log performance warning for slow requests
        $slowThreshold = config('cashkdiopen.logging.slow_query_threshold', 1000); // 1 second default
        if ($processingTime > $slowThreshold) {
            $logData['performance_warning'] = 'slow_request';
            Log::warning('Slow API request detected', $logData);
        }

        Log::log($logLevel, 'API response sent', $logData);
    }

    /**
     * Determine log level based on HTTP status code.
     */
    protected function getLogLevelForStatus(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            $statusCode >= 300 => 'info',
            default => 'info',
        };
    }

    /**
     * Check if response is JSON.
     */
    protected function isJsonResponse($response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * Sanitize sensitive data from arrays.
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveFields = config('cashkdiopen.logging.sensitive_fields', [
            'password',
            'pin',
            'secret',
            'token',
            'api_key',
            'key',
            'authorization',
            'x-api-key',
            'customer_phone', // Will be partially masked
            'phone',
            'card_number',
            'cvv',
            'card_holder_name',
        ]);

        return $this->recursiveSanitize($data, $sensitiveFields);
    }

    /**
     * Recursively sanitize sensitive data.
     */
    protected function recursiveSanitize(array $data, array $sensitiveFields): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            if (in_array($lowerKey, array_map('strtolower', $sensitiveFields))) {
                // Special handling for phone numbers (partial masking)
                if (in_array($lowerKey, ['customer_phone', 'phone']) && is_string($value)) {
                    $data[$key] = $this->maskPhoneNumber($value);
                } else {
                    $data[$key] = '***MASKED***';
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveFields);
            }
        }

        return $data;
    }

    /**
     * Mask phone number (show first 4 and last 2 digits).
     */
    protected function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return '***MASKED***';
        }

        $start = substr($phone, 0, 4);
        $end = substr($phone, -2);
        $middle = str_repeat('*', strlen($phone) - 6);

        return $start . $middle . $end;
    }
}