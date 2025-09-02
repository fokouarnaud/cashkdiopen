<?php

namespace Cashkdiopen\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Cashkdiopen\Laravel\Services\PaymentService;
use Cashkdiopen\Laravel\Http\Requests\CreatePaymentRequest;
use Cashkdiopen\Laravel\Http\Requests\ListPaymentsRequest;
use Cashkdiopen\Laravel\Models\Transaction;
use Cashkdiopen\Laravel\Exceptions\PaymentException;
use Cashkdiopen\Laravel\Exceptions\ProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentController extends BaseController
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create a new payment transaction.
     */
    public function create(CreatePaymentRequest $request): JsonResponse
    {
        try {
            $transaction = $this->paymentService->createPayment($request->validated());

            Log::info('Payment created successfully', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'provider' => $transaction->provider,
                'amount' => $transaction->amount,
                'api_key_id' => $request->apiKey->key_id,
            ]);

            return $this->successResponse([
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'method' => $transaction->provider,
                'status' => $transaction->status,
                'redirect_url' => $transaction->provider_response['redirect_url'] ?? null,
                'expires_at' => $transaction->expires_at->toISOString(),
                'created_at' => $transaction->created_at->toISOString(),
            ], 'Payment created successfully', 201);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (PaymentException $e) {
            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'data' => $request->validated(),
            ]);

            return $this->errorResponse(
                'PAYMENT_CREATION_FAILED',
                $e->getMessage(),
                422
            );
        } catch (ProviderException $e) {
            Log::error('Provider error during payment creation', [
                'error' => $e->getMessage(),
                'provider' => $request->validated()['method'] ?? 'unknown',
            ]);

            return $this->errorResponse(
                'PROVIDER_UNAVAILABLE',
                'Payment provider is temporarily unavailable. Please try again.',
                502
            );
        } catch (\Exception $e) {
            Log::error('Unexpected error during payment creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'An unexpected error occurred. Please try again.',
                500
            );
        }
    }

    /**
     * Get payment details.
     */
    public function show(Request $request, string $payment): JsonResponse
    {
        try {
            $transaction = $this->paymentService->getPayment($payment);

            if (!$transaction) {
                return $this->errorResponse(
                    'TRANSACTION_NOT_FOUND',
                    'Transaction not found',
                    404
                );
            }

            // Check if the API key has access to this transaction
            if (!$this->canAccessTransaction($request, $transaction)) {
                return $this->errorResponse(
                    'TRANSACTION_ACCESS_DENIED',
                    'Access denied to this transaction',
                    403
                );
            }

            return $this->successResponse([
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'method' => $transaction->provider,
                'status' => $transaction->status,
                'customer_phone' => $transaction->masked_phone,
                'description' => $transaction->description,
                'provider_reference' => $transaction->provider_reference,
                'redirect_url' => $transaction->provider_response['redirect_url'] ?? null,
                'expires_at' => $transaction->expires_at->toISOString(),
                'completed_at' => $transaction->completed_at?->toISOString(),
                'created_at' => $transaction->created_at->toISOString(),
                'metadata' => $transaction->metadata,
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving payment', [
                'payment_id' => $payment,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving payment details',
                500
            );
        }
    }

    /**
     * Get payment status only.
     */
    public function status(Request $request, string $payment): JsonResponse
    {
        try {
            $cacheKey = "payment_status_{$payment}";
            
            // Cache status for 5 minutes to reduce API calls
            $statusData = Cache::remember($cacheKey, 300, function () use ($payment, $request) {
                $transaction = $this->paymentService->getPayment($payment);
                
                if (!$transaction) {
                    return null;
                }

                // Check access
                if (!$this->canAccessTransaction($request, $transaction)) {
                    return 'access_denied';
                }

                // Refresh status from provider if transaction is not final
                if (!$transaction->isFinal()) {
                    $transaction = $this->paymentService->refreshPaymentStatus($transaction);
                }

                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'provider_reference' => $transaction->provider_reference,
                    'completed_at' => $transaction->completed_at?->toISOString(),
                    'last_checked_at' => now()->toISOString(),
                ];
            });

            if ($statusData === null) {
                return $this->errorResponse(
                    'TRANSACTION_NOT_FOUND',
                    'Transaction not found',
                    404
                );
            }

            if ($statusData === 'access_denied') {
                return $this->errorResponse(
                    'TRANSACTION_ACCESS_DENIED',
                    'Access denied to this transaction',
                    403
                );
            }

            return $this->successResponse($statusData);

        } catch (\Exception $e) {
            Log::error('Error retrieving payment status', [
                'payment_id' => $payment,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving payment status',
                500
            );
        }
    }

    /**
     * List payments with filtering and pagination.
     */
    public function index(ListPaymentsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $payments = $this->paymentService->listPayments($filters, $request->apiKey);

            $data = $payments->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'method' => $transaction->provider,
                    'status' => $transaction->status,
                    'completed_at' => $transaction->completed_at?->toISOString(),
                    'created_at' => $transaction->created_at->toISOString(),
                ];
            });

            return $this->successResponse($data->items(), 'Payments retrieved successfully', 200, [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing payments', [
                'filters' => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving payments',
                500
            );
        }
    }

    /**
     * Cancel a payment (if supported by provider).
     */
    public function cancel(Request $request, string $payment): JsonResponse
    {
        try {
            $transaction = $this->paymentService->getPayment($payment);

            if (!$transaction) {
                return $this->errorResponse(
                    'TRANSACTION_NOT_FOUND',
                    'Transaction not found',
                    404
                );
            }

            if (!$this->canAccessTransaction($request, $transaction)) {
                return $this->errorResponse(
                    'TRANSACTION_ACCESS_DENIED',
                    'Access denied to this transaction',
                    403
                );
            }

            if (!$transaction->canBeCanceled()) {
                return $this->errorResponse(
                    'TRANSACTION_CANNOT_BE_CANCELED',
                    'Transaction cannot be canceled in its current state',
                    422
                );
            }

            $canceledTransaction = $this->paymentService->cancelPayment($transaction);

            Log::info('Payment canceled', [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->reference,
                'api_key_id' => $request->apiKey->key_id,
            ]);

            return $this->successResponse([
                'id' => $canceledTransaction->id,
                'reference' => $canceledTransaction->reference,
                'status' => $canceledTransaction->status,
                'completed_at' => $canceledTransaction->completed_at->toISOString(),
            ], 'Payment canceled successfully');

        } catch (PaymentException $e) {
            return $this->errorResponse(
                'PAYMENT_CANCEL_FAILED',
                $e->getMessage(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Error canceling payment', [
                'payment_id' => $payment,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error canceling payment',
                500
            );
        }
    }

    /**
     * Get available payment providers.
     */
    public function providers(): JsonResponse
    {
        try {
            $providers = $this->paymentService->getAvailableProviders();

            return $this->successResponse($providers);

        } catch (\Exception $e) {
            Log::error('Error retrieving providers', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving providers',
                500
            );
        }
    }

    /**
     * Get provider information and capabilities.
     */
    public function providerInfo(string $provider): JsonResponse
    {
        try {
            $providerInfo = $this->paymentService->getProviderInfo($provider);

            if (!$providerInfo) {
                return $this->errorResponse(
                    'PROVIDER_NOT_FOUND',
                    'Provider not found',
                    404
                );
            }

            return $this->successResponse($providerInfo);

        } catch (\Exception $e) {
            Log::error('Error retrieving provider info', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving provider information',
                500
            );
        }
    }

    /**
     * Validate phone number for mobile money providers.
     */
    public function validatePhone(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'provider' => 'required|string|in:orange_money,mtn_momo',
        ]);

        try {
            $isValid = $this->paymentService->validatePhoneNumber(
                $request->phone,
                $request->provider
            );

            return $this->successResponse([
                'phone' => $request->phone,
                'provider' => $request->provider,
                'is_valid' => $isValid,
                'formatted_phone' => $this->paymentService->formatPhoneNumber($request->phone),
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating phone number', [
                'phone' => $request->phone,
                'provider' => $request->provider,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Error validating phone number',
                422
            );
        }
    }

    /**
     * Get supported currencies for a provider.
     */
    public function currencies(string $provider = null): JsonResponse
    {
        try {
            $currencies = $this->paymentService->getSupportedCurrencies($provider);

            return $this->successResponse([
                'provider' => $provider,
                'currencies' => $currencies,
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving currencies', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving currencies',
                500
            );
        }
    }

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        try {
            $healthStatus = $this->paymentService->getHealthStatus();

            $httpStatus = $healthStatus['status'] === 'healthy' ? 200 : 503;

            return response()->json([
                'status' => $healthStatus['status'],
                'timestamp' => now()->toISOString(),
                'services' => $healthStatus['services'],
                'version' => config('cashkdiopen.version', '1.0.0'),
            ], $httpStatus);

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => 'Health check failed',
            ], 503);
        }
    }

    /**
     * Check if the current API key can access the transaction.
     */
    protected function canAccessTransaction(Request $request, Transaction $transaction): bool
    {
        // If the transaction was created with the same API key, allow access
        if ($transaction->api_key_id === $request->apiKey->id) {
            return true;
        }

        // If the API key has admin read scope, allow access
        if ($request->apiKey->hasScope('admin:read')) {
            return true;
        }

        // If the API key belongs to the same user as the transaction creator
        if ($transaction->apiKey && $transaction->apiKey->user_id === $request->apiKey->user_id) {
            return true;
        }

        return false;
    }
}