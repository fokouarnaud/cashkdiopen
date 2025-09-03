<?php

namespace Cashkdiopen\Payments\Services;

use Cashkdiopen\Payments\Contracts\PaymentProviderInterface;
use Cashkdiopen\Payments\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected ProviderFactory $providerFactory,
        protected array $config
    ) {}

    /**
     * Create a new payment.
     */
    public function createPayment(array $data): Payment
    {
        $provider = $this->providerFactory->make($data['provider']);
        
        // Generate unique reference
        $reference = $this->generateReference();
        
        $payment = Payment::create([
            'reference' => $reference,
            'provider' => $data['provider'],
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'description' => $data['description'] ?? null,
            'callback_url' => $data['callback_url'] ?? null,
            'return_url' => $data['return_url'] ?? null,
            'status' => 'pending',
            'metadata' => $data['metadata'] ?? [],
            'expires_at' => now()->addMinutes($this->config['payment_timeout'] ?? 30),
        ]);

        try {
            $providerResponse = $provider->createPayment([
                'reference' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'description' => $data['description'] ?? null,
                'callback_url' => $data['callback_url'] ?? null,
                'return_url' => $data['return_url'] ?? null,
            ]);

            $payment->update([
                'external_id' => $providerResponse['external_id'] ?? null,
                'provider_reference' => $providerResponse['provider_reference'] ?? null,
                'provider_data' => $providerResponse['provider_data'] ?? [],
                'status' => $providerResponse['status'] ?? 'pending',
            ]);
        } catch (\Exception $e) {
            $payment->update([
                'status' => 'failed',
                'provider_data' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        return $payment->fresh();
    }

    /**
     * List payments with filtering.
     */
    public function listPayments(array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query();

        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        if (!empty($filters['provider'])) {
            $query->provider($filters['provider']);
        }

        if (!empty($filters['currency'])) {
            $query->currency($filters['currency']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->latest()
            ->with(['transaction'])
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Cancel a payment.
     */
    public function cancelPayment(Payment $payment): array
    {
        if (!$payment->isPending()) {
            throw new \Exception('Only pending payments can be cancelled');
        }

        $provider = $this->providerFactory->make($payment->provider);
        
        try {
            $result = $provider->cancelPayment($payment->provider_reference);
            
            $payment->update([
                'status' => 'cancelled',
                'processed_at' => now(),
            ]);

            return $result;
        } catch (\Exception $e) {
            throw new \Exception('Failed to cancel payment: ' . $e->getMessage());
        }
    }

    /**
     * Get available providers.
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->config['providers'] ?? []);
    }

    /**
     * Get provider information.
     */
    public function getProviderInfo(string $providerName): array
    {
        $provider = $this->providerFactory->make($providerName);
        
        return [
            'name' => $providerName,
            'capabilities' => $provider->getCapabilities(),
            'supported_currencies' => $provider->getSupportedCurrencies(),
        ];
    }

    /**
     * Validate phone number for mobile money.
     */
    public function validatePhoneNumber(string $phone, string $providerName): bool
    {
        $provider = $this->providerFactory->make($providerName);
        
        return $provider->validatePhoneNumber($phone);
    }

    /**
     * Get supported currencies.
     */
    public function getSupportedCurrencies(?string $providerName = null): array
    {
        if ($providerName) {
            $provider = $this->providerFactory->make($providerName);
            return $provider->getSupportedCurrencies();
        }

        $currencies = [];
        foreach ($this->getAvailableProviders() as $providerName) {
            $provider = $this->providerFactory->make($providerName);
            $currencies = array_merge($currencies, $provider->getSupportedCurrencies());
        }

        return array_unique($currencies);
    }

    /**
     * Generate unique payment reference.
     */
    protected function generateReference(): string
    {
        do {
            $reference = 'CKD_' . strtoupper(Str::random(12));
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }
}