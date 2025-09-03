<?php

namespace Cashkdiopen\Payments\Http\Controllers;

use Cashkdiopen\Payments\Http\Requests\CreatePaymentRequest;
use Cashkdiopen\Payments\Http\Requests\ListPaymentsRequest;
use Cashkdiopen\Payments\Models\Payment;
use Cashkdiopen\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a new payment.
     */
    public function create(CreatePaymentRequest $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->createPayment($request->validated());
            
            return $this->success($payment, 'Payment created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create payment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Show payment details.
     */
    public function show(Payment $payment): JsonResponse
    {
        return $this->success($payment->load(['transaction']));
    }

    /**
     * Get payment status.
     */
    public function status(Payment $payment): JsonResponse
    {
        return $this->success([
            'id' => $payment->id,
            'status' => $payment->status,
            'provider' => $payment->provider,
            'updated_at' => $payment->updated_at,
        ]);
    }

    /**
     * List payments with filtering.
     */
    public function index(ListPaymentsRequest $request): JsonResponse
    {
        $payments = $this->paymentService->listPayments($request->validated());
        
        return $this->success($payments);
    }

    /**
     * Cancel a payment.
     */
    public function cancel(Payment $payment): JsonResponse
    {
        try {
            $result = $this->paymentService->cancelPayment($payment);
            
            return $this->success($result, 'Payment cancellation initiated');
        } catch (\Exception $e) {
            return $this->error('Failed to cancel payment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get available providers.
     */
    public function providers(): JsonResponse
    {
        $providers = $this->paymentService->getAvailableProviders();
        
        return $this->success($providers);
    }

    /**
     * Get provider information.
     */
    public function providerInfo(string $provider): JsonResponse
    {
        try {
            $info = $this->paymentService->getProviderInfo($provider);
            
            return $this->success($info);
        } catch (\Exception $e) {
            return $this->error('Provider not found', null, 404);
        }
    }

    /**
     * Validate phone number for mobile money.
     */
    public function validatePhone(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'provider' => 'required|string|in:orange-money,mtn-momo',
        ]);

        $isValid = $this->paymentService->validatePhoneNumber(
            $request->phone,
            $request->provider
        );

        return $this->success([
            'phone' => $request->phone,
            'provider' => $request->provider,
            'is_valid' => $isValid,
        ]);
    }

    /**
     * Get supported currencies.
     */
    public function currencies(?string $provider = null): JsonResponse
    {
        $currencies = $this->paymentService->getSupportedCurrencies($provider);
        
        return $this->success($currencies);
    }

    /**
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        return $this->success([
            'status' => 'healthy',
            'timestamp' => now(),
            'version' => '1.0.0',
        ], 'Cashkdiopen API is healthy');
    }
}