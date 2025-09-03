<?php

namespace Cashkdiopen\Payments\Services;

use Cashkdiopen\Payments\Models\Payment;
use Cashkdiopen\Payments\Models\WebhookLog;
use Illuminate\Http\Request;

class WebhookService
{
    public function __construct(
        protected SignatureService $signatureService,
        protected array $config
    ) {}

    /**
     * Process incoming webhook.
     */
    public function processWebhook(string $provider, Request $request): array
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        // Log the webhook
        $webhookLog = WebhookLog::create([
            'provider' => $provider,
            'event_type' => $payload['event_type'] ?? 'unknown',
            'payload' => $payload,
            'headers' => $headers,
            'status' => 'pending',
        ]);

        try {
            // Verify signature if configured
            if ($this->config['verify_signatures'] ?? true) {
                $this->signatureService->verifyWebhookSignature($provider, $request);
            }

            // Process the webhook based on provider
            $result = $this->processProviderWebhook($provider, $payload, $webhookLog);

            $webhookLog->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            return $result;
        } catch (\Exception $e) {
            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'next_retry_at' => now()->addMinutes(5),
            ]);

            throw $e;
        }
    }

    /**
     * Process webhook specific to provider.
     */
    protected function processProviderWebhook(string $provider, array $payload, WebhookLog $webhookLog): array
    {
        // Extract payment reference from payload
        $paymentReference = $this->extractPaymentReference($provider, $payload);
        
        if (!$paymentReference) {
            throw new \Exception('Payment reference not found in webhook payload');
        }

        $payment = Payment::where('reference', $paymentReference)
            ->orWhere('provider_reference', $paymentReference)
            ->first();

        if (!$payment) {
            throw new \Exception("Payment not found for reference: {$paymentReference}");
        }

        // Update webhook log with payment
        $webhookLog->update(['payment_id' => $payment->id]);

        // Process payment status update
        return $this->updatePaymentFromWebhook($payment, $provider, $payload);
    }

    /**
     * Update payment status from webhook data.
     */
    protected function updatePaymentFromWebhook(Payment $payment, string $provider, array $payload): array
    {
        $status = $this->extractPaymentStatus($provider, $payload);
        $transactionData = $this->extractTransactionData($provider, $payload);

        $payment->update([
            'status' => $status,
            'processed_at' => now(),
            'provider_data' => array_merge($payment->provider_data ?? [], $payload),
        ]);

        // Create or update transaction if transaction data is available
        if ($transactionData) {
            $payment->transaction()->updateOrCreate(
                ['payment_id' => $payment->id],
                $transactionData
            );
        }

        return [
            'payment_id' => $payment->id,
            'status' => $status,
            'processed_at' => $payment->processed_at,
        ];
    }

    /**
     * Extract payment reference from webhook payload.
     */
    protected function extractPaymentReference(string $provider, array $payload): ?string
    {
        $referenceFields = [
            'reference',
            'payment_reference',
            'transaction_reference',
            'external_reference',
            'merchant_reference',
        ];

        foreach ($referenceFields as $field) {
            if (!empty($payload[$field])) {
                return $payload[$field];
            }
        }

        return null;
    }

    /**
     * Extract payment status from webhook payload.
     */
    protected function extractPaymentStatus(string $provider, array $payload): string
    {
        $status = $payload['status'] ?? $payload['transaction_status'] ?? 'unknown';

        // Normalize status based on provider
        return match ($provider) {
            'orange-money' => $this->normalizeOrangeMoneyStatus($status),
            'mtn-momo' => $this->normalizeMtnMoMoStatus($status),
            'cards' => $this->normalizeCardsStatus($status),
            default => $this->normalizeGenericStatus($status),
        };
    }

    /**
     * Extract transaction data from webhook payload.
     */
    protected function extractTransactionData(string $provider, array $payload): ?array
    {
        if (empty($payload['transaction_id']) && empty($payload['provider_transaction_id'])) {
            return null;
        }

        return [
            'provider_transaction_id' => $payload['transaction_id'] ?? $payload['provider_transaction_id'],
            'type' => $payload['transaction_type'] ?? 'payment',
            'status' => $this->extractPaymentStatus($provider, $payload),
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'XAF',
            'fees' => $payload['fees'] ?? 0,
            'provider_fees' => $payload['provider_fees'] ?? 0,
            'net_amount' => $payload['net_amount'] ?? ($payload['amount'] ?? 0) - ($payload['fees'] ?? 0),
            'provider_data' => $payload,
            'processed_at' => now(),
        ];
    }

    /**
     * Retry failed webhook processing.
     */
    public function retryWebhook(WebhookLog $webhookLog): array
    {
        if (!$webhookLog->canRetry()) {
            throw new \Exception('Webhook cannot be retried');
        }

        $webhookLog->incrementRetryCount();

        try {
            $result = $this->processProviderWebhook(
                $webhookLog->provider,
                $webhookLog->payload,
                $webhookLog
            );

            $webhookLog->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return $result;
        } catch (\Exception $e) {
            $webhookLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Retry multiple failed webhooks.
     */
    public function retryFailedWebhooks(?string $provider = null, int $limit = 50): int
    {
        $query = WebhookLog::failedReadyForRetry()->limit($limit);

        if ($provider) {
            $query->provider($provider);
        }

        $webhooks = $query->get();
        $retried = 0;

        foreach ($webhooks as $webhook) {
            try {
                $this->retryWebhook($webhook);
                $retried++;
            } catch (\Exception $e) {
                // Continue with next webhook
                continue;
            }
        }

        return $retried;
    }

    /**
     * Normalize status methods for different providers.
     */
    protected function normalizeOrangeMoneyStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful', 'success', 'completed' => 'completed',
            'failed', 'error', 'declined' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'pending',
        };
    }

    protected function normalizeMtnMoMoStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful', 'success', 'completed' => 'completed',
            'failed', 'error', 'declined' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            default => 'pending',
        };
    }

    protected function normalizeCardsStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful', 'success', 'completed', 'captured' => 'completed',
            'failed', 'error', 'declined', 'rejected' => 'failed',
            'cancelled', 'canceled', 'voided' => 'cancelled',
            default => 'pending',
        };
    }

    protected function normalizeGenericStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful', 'success', 'completed', 'paid' => 'completed',
            'failed', 'error', 'declined', 'rejected' => 'failed',
            'cancelled', 'canceled', 'voided' => 'cancelled',
            default => 'pending',
        };
    }
}