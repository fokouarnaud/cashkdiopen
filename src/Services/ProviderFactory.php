<?php

namespace Cashkdiopen\Payments\Services;

use Cashkdiopen\Payments\Contracts\PaymentProviderInterface;

class ProviderFactory
{
    protected array $providers = [];

    public function __construct(protected array $config = [])
    {
        $this->registerDefaultProviders();
    }

    /**
     * Make a provider instance.
     */
    public function make(string $provider): PaymentProviderInterface
    {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Payment provider '{$provider}' is not registered");
        }

        $providerClass = $this->providers[$provider];
        $providerConfig = $this->config[$provider] ?? [];

        return new $providerClass($providerConfig);
    }

    /**
     * Register a payment provider.
     */
    public function register(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new \Exception("Provider class '{$class}' does not exist");
        }

        if (!in_array(PaymentProviderInterface::class, class_implements($class))) {
            throw new \Exception("Provider class '{$class}' must implement PaymentProviderInterface");
        }

        $this->providers[$name] = $class;
    }

    /**
     * Get all registered providers.
     */
    public function getRegisteredProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if provider is registered.
     */
    public function hasProvider(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }

    /**
     * Register default providers.
     */
    protected function registerDefaultProviders(): void
    {
        // Note: These providers would be implemented separately
        // For now, we'll use mock providers for demonstration
        
        $this->providers = [
            'orange-money' => MockOrangeMoneyProvider::class,
            'mtn-momo' => MockMtnMoMoProvider::class,
            'cards' => MockCardsProvider::class,
        ];
    }
}

/**
 * Mock provider implementations for demonstration
 * In a real implementation, these would be separate provider classes
 */
class MockOrangeMoneyProvider implements PaymentProviderInterface
{
    public function __construct(protected array $config = []) {}

    public function createPayment(array $data): array
    {
        return [
            'external_id' => 'OM_' . uniqid(),
            'provider_reference' => 'OM_REF_' . uniqid(),
            'status' => 'pending',
            'provider_data' => [
                'payment_url' => 'https://orange-money.example.com/pay/' . uniqid(),
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ],
        ];
    }

    public function getPaymentStatus(string $providerReference): array
    {
        return [
            'status' => 'pending',
            'provider_reference' => $providerReference,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function cancelPayment(string $providerReference): array
    {
        return [
            'status' => 'cancelled',
            'provider_reference' => $providerReference,
            'cancelled_at' => now()->toISOString(),
        ];
    }

    public function validatePhoneNumber(string $phone): bool
    {
        return preg_match('/^\+237[67]\d{8}$/', $phone);
    }

    public function getSupportedCurrencies(): array
    {
        return ['XAF'];
    }

    public function getCapabilities(): array
    {
        return [
            'create_payment' => true,
            'cancel_payment' => true,
            'refund_payment' => false,
            'recurring_payment' => false,
        ];
    }

    public function processWebhook(array $payload, array $headers = []): array
    {
        return [
            'status' => $payload['status'] ?? 'pending',
            'provider_reference' => $payload['transaction_id'] ?? null,
        ];
    }
}

class MockMtnMoMoProvider implements PaymentProviderInterface
{
    public function __construct(protected array $config = []) {}

    public function createPayment(array $data): array
    {
        return [
            'external_id' => 'MTN_' . uniqid(),
            'provider_reference' => 'MTN_REF_' . uniqid(),
            'status' => 'pending',
            'provider_data' => [
                'payment_url' => 'https://mtn-momo.example.com/pay/' . uniqid(),
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ],
        ];
    }

    public function getPaymentStatus(string $providerReference): array
    {
        return [
            'status' => 'pending',
            'provider_reference' => $providerReference,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function cancelPayment(string $providerReference): array
    {
        return [
            'status' => 'cancelled',
            'provider_reference' => $providerReference,
            'cancelled_at' => now()->toISOString(),
        ];
    }

    public function validatePhoneNumber(string $phone): bool
    {
        return preg_match('/^\+237[67]\d{8}$/', $phone);
    }

    public function getSupportedCurrencies(): array
    {
        return ['XAF'];
    }

    public function getCapabilities(): array
    {
        return [
            'create_payment' => true,
            'cancel_payment' => true,
            'refund_payment' => false,
            'recurring_payment' => false,
        ];
    }

    public function processWebhook(array $payload, array $headers = []): array
    {
        return [
            'status' => $payload['status'] ?? 'pending',
            'provider_reference' => $payload['transaction_id'] ?? null,
        ];
    }
}

class MockCardsProvider implements PaymentProviderInterface
{
    public function __construct(protected array $config = []) {}

    public function createPayment(array $data): array
    {
        return [
            'external_id' => 'CARD_' . uniqid(),
            'provider_reference' => 'CARD_REF_' . uniqid(),
            'status' => 'pending',
            'provider_data' => [
                'payment_url' => 'https://cards.example.com/pay/' . uniqid(),
                'expires_at' => now()->addMinutes(30)->toISOString(),
            ],
        ];
    }

    public function getPaymentStatus(string $providerReference): array
    {
        return [
            'status' => 'pending',
            'provider_reference' => $providerReference,
            'updated_at' => now()->toISOString(),
        ];
    }

    public function cancelPayment(string $providerReference): array
    {
        return [
            'status' => 'cancelled',
            'provider_reference' => $providerReference,
            'cancelled_at' => now()->toISOString(),
        ];
    }

    public function validatePhoneNumber(string $phone): bool
    {
        return true; // Cards don't require phone validation
    }

    public function getSupportedCurrencies(): array
    {
        return ['XAF', 'EUR', 'USD'];
    }

    public function getCapabilities(): array
    {
        return [
            'create_payment' => true,
            'cancel_payment' => true,
            'refund_payment' => true,
            'recurring_payment' => true,
        ];
    }

    public function processWebhook(array $payload, array $headers = []): array
    {
        return [
            'status' => $payload['status'] ?? 'pending',
            'provider_reference' => $payload['transaction_id'] ?? null,
        ];
    }
}