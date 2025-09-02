<?php

namespace Cashkdiopen\Laravel\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Return a successful JSON response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = array_merge($meta, [
                'request_id' => $this->getRequestId(),
                'timestamp' => now()->toISOString(),
            ]);
        } else {
            $response['meta'] = [
                'request_id' => $this->getRequestId(),
                'timestamp' => now()->toISOString(),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     */
    protected function errorResponse(
        string $code,
        string $message,
        int $statusCode = 400,
        array $details = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => [
                'request_id' => $this->getRequestId(),
                'timestamp' => now()->toISOString(),
            ],
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(array $errors): JsonResponse
    {
        return $this->errorResponse(
            'VALIDATION_FAILED',
            'The provided data is invalid',
            422,
            $errors
        );
    }

    /**
     * Return a not found error response.
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse(
            'NOT_FOUND',
            "{$resource} not found",
            404
        );
    }

    /**
     * Return an unauthorized error response.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse(
            'UNAUTHORIZED',
            $message,
            401
        );
    }

    /**
     * Return a forbidden error response.
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse(
            'FORBIDDEN',
            $message,
            403
        );
    }

    /**
     * Return a rate limit exceeded response.
     */
    protected function rateLimitResponse(int $retryAfter = 3600): JsonResponse
    {
        $response = $this->errorResponse(
            'RATE_LIMIT_EXCEEDED',
            'Too many requests. Please try again later.',
            429
        );

        return $response->header('Retry-After', $retryAfter);
    }

    /**
     * Get the current request ID.
     */
    protected function getRequestId(): string
    {
        return request()->header('X-Request-ID') 
            ?? request()->header('X-Correlation-ID') 
            ?? 'req_' . Str::random(12);
    }

    /**
     * Get the current API key from request.
     */
    protected function getCurrentApiKey()
    {
        return request()->apiKey ?? null;
    }

    /**
     * Check if the current request is from a production environment.
     */
    protected function isProductionRequest(): bool
    {
        $apiKey = $this->getCurrentApiKey();
        return $apiKey && $apiKey->isProduction();
    }

    /**
     * Get pagination metadata from a paginator.
     */
    protected function getPaginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}