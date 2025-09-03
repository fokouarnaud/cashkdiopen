<?php

namespace Cashkdiopen\Payments\Contracts;

interface PaymentProviderInterface
{
    /**
     * Create a new payment.
     */
    public function createPayment(array $data): array;

    /**
     * Get payment status.
     */
    public function getPaymentStatus(string $providerReference): array;

    /**
     * Cancel a payment (if supported).
     */
    public function cancelPayment(string $providerReference): array;

    /**
     * Validate phone number format for this provider.
     */
    public function validatePhoneNumber(string $phone): bool;

    /**
     * Get supported currencies for this provider.
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get provider capabilities.
     */
    public function getCapabilities(): array;

    /**
     * Process webhook data.
     */
    public function processWebhook(array $payload, array $headers = []): array;
}