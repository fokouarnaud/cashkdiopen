<?php

namespace Cashkdiopen\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Cashkdiopen\Laravel\Services\WebhookService;
use Cashkdiopen\Laravel\Services\PaymentService;
use Cashkdiopen\Laravel\Models\WebhookLog;
use Cashkdiopen\Laravel\Models\Transaction;
use Cashkdiopen\Laravel\Events\WebhookReceived;
use Cashkdiopen\Laravel\Jobs\ProcessWebhookJob;
use Cashkdiopen\Laravel\Exceptions\WebhookException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class WebhookController extends BaseController
{
    protected WebhookService $webhookService;
    protected PaymentService $paymentService;

    public function __construct(
        WebhookService $webhookService,
        PaymentService $paymentService
    ) {
        $this->webhookService = $webhookService;
        $this->paymentService = $paymentService;
    }

    /**
     * Handle Orange Money webhooks.
     */
    public function handleOrangeMoney(Request $request): Response
    {
        return $this->handleProviderWebhook($request, 'orange_money');
    }

    /**
     * Handle MTN Mobile Money webhooks.
     */
    public function handleMtnMoMo(Request $request): Response
    {
        return $this->handleProviderWebhook($request, 'mtn_momo');
    }

    /**
     * Handle Bank Cards webhooks.
     */
    public function handleCards(Request $request): Response
    {
        return $this->handleProviderWebhook($request, 'cards');
    }

    /**
     * Handle generic provider webhook (auto-detect provider).
     */
    public function handleGeneric(Request $request, string $provider): Response
    {
        return $this->handleProviderWebhook($request, $provider);
    }

    /**
     * Handle webhook from any provider.
     */
    protected function handleProviderWebhook(Request $request, string $provider): Response
    {
        $startTime = microtime(true);
        $payload = $request->getContent();
        $headers = $request->headers->all();
        
        // Generate unique webhook ID for tracking
        $webhookId = 'whk_' . \Illuminate\Support\Str::random(12);
        
        Log::info('Webhook received', [
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'content_length' => strlen($payload),
            'headers' => $this->sanitizeHeaders($headers),
        ]);

        try {
            // Basic validation
            if (empty($payload)) {
                throw new WebhookException('Empty webhook payload');
            }

            // Decode JSON payload
            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new WebhookException('Invalid JSON payload: ' . json_last_error_msg());
            }

            // Extract event type
            $eventType = $this->extractEventType($data, $provider);

            // Find related transaction
            $transaction = $this->findRelatedTransaction($data, $provider);

            // Create webhook log
            $webhookLog = WebhookLog::create([
                'transaction_id' => $transaction?->id,
                'provider' => $provider,
                'event_type' => $eventType,
                'payload' => $data,
                'headers' => $headers,
                'signature' => $request->header('X-Cashkdiopen-Signature'),
                'status' => WebhookLog::STATUS_PENDING,
            ]);

            // Fire webhook received event
            event(new WebhookReceived($webhookLog, $data));

            // Queue webhook processing for asynchronous handling
            if (config('cashkdiopen.queue.enabled', true)) {
                Queue::push(new ProcessWebhookJob($webhookLog->id));
                
                Log::info('Webhook queued for processing', [
                    'webhook_id' => $webhookId,
                    'webhook_log_id' => $webhookLog->id,
                    'provider' => $provider,
                    'event_type' => $eventType,
                ]);
            } else {
                // Process immediately if queue is disabled
                $this->processWebhookSync($webhookLog);
            }

            // Log processing time
            $processingTime = (microtime(true) - $startTime) * 1000;
            Log::info('Webhook handled successfully', [
                'webhook_id' => $webhookId,
                'provider' => $provider,
                'processing_time_ms' => $processingTime,
            ]);

            // Return 200 OK immediately to acknowledge receipt
            return response('OK', 200);

        } catch (WebhookException $e) {
            Log::warning('Webhook validation failed', [
                'webhook_id' => $webhookId,
                'provider' => $provider,
                'error' => $e->getMessage(),
                'payload_preview' => substr($payload, 0, 200),
            ]);

            return response('Bad Request', 400);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'webhook_id' => $webhookId,
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * Process webhook synchronously (for testing or when queue is disabled).
     */
    protected function processWebhookSync(WebhookLog $webhookLog): void
    {
        try {
            $webhookLog->markAsProcessing();
            
            $result = $this->webhookService->processWebhook($webhookLog);
            
            if ($result) {
                $webhookLog->markAsSuccessful();
            } else {
                $webhookLog->markAsFailed('Processing returned false');
            }

        } catch (\Exception $e) {
            $webhookLog->markAsFailed($e->getMessage());
            
            Log::error('Synchronous webhook processing failed', [
                'webhook_log_id' => $webhookLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract event type from webhook payload.
     */
    protected function extractEventType(array $data, string $provider): string
    {
        return match($provider) {
            'orange_money' => $data['event'] ?? $data['eventType'] ?? 'unknown',
            'mtn_momo' => $data['event'] ?? $data['status'] ?? 'unknown',
            'cards' => $data['type'] ?? $data['event'] ?? 'unknown',
            default => $data['event'] ?? 'unknown',
        };
    }

    /**
     * Find the transaction related to this webhook.
     */
    protected function findRelatedTransaction(array $data, string $provider): ?Transaction
    {
        // Try different ways to identify the transaction based on provider
        $referenceFields = match($provider) {
            'orange_money' => ['reference', 'merchantTransId', 'transaction_id'],
            'mtn_momo' => ['externalId', 'reference', 'transaction_id'],
            'cards' => ['reference', 'merchant_reference', 'transaction_id'],
            default => ['reference', 'transaction_id', 'external_id'],
        };

        foreach ($referenceFields as $field) {
            if (isset($data[$field])) {
                $transaction = Transaction::where('reference', $data[$field])->first();
                if ($transaction) {
                    return $transaction;
                }
            }
        }

        // Try provider reference
        $providerReferenceFields = ['provider_reference', 'payment_id', 'transaction_id'];
        foreach ($providerReferenceFields as $field) {
            if (isset($data[$field])) {
                $transaction = Transaction::where('provider_reference', $data[$field])->first();
                if ($transaction) {
                    return $transaction;
                }
            }
        }

        return null;
    }

    /**
     * Sanitize headers for logging (remove sensitive data).
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-secret',
            'x-cashkdiopen-signature'
        ];

        $sanitized = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '***REDACTED***';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get webhook logs (admin endpoint).
     */
    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'sometimes|string',
            'status' => 'sometimes|string',
            'event_type' => 'sometimes|string',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $query = WebhookLog::with('transaction:id,reference')
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->provider) {
                $query->withProvider($request->provider);
            }

            if ($request->status) {
                $query->withStatus($request->status);
            }

            if ($request->event_type) {
                $query->withEventType($request->event_type);
            }

            if ($request->date_from) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $webhookLogs = $query->paginate($request->per_page ?? 20);

            $data = $webhookLogs->through(function ($log) {
                return [
                    'id' => $log->id,
                    'provider' => $log->provider_display_name,
                    'event_type' => $log->event_type_display_name,
                    'status' => $log->status_display,
                    'transaction_reference' => $log->transaction?->reference,
                    'retry_count' => $log->retry_count,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at->toISOString(),
                    'processed_at' => $log->processed_at?->toISOString(),
                ];
            });

            return $this->successResponse(
                $data->items(),
                'Webhook logs retrieved successfully',
                200,
                $this->getPaginationMeta($webhookLogs)
            );

        } catch (\Exception $e) {
            Log::error('Error retrieving webhook logs', [
                'error' => $e->getMessage(),
                'filters' => $request->validated(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving webhook logs',
                500
            );
        }
    }

    /**
     * Get webhook log details (admin endpoint).
     */
    public function showLog(WebhookLog $webhookLog): JsonResponse
    {
        try {
            return $this->successResponse([
                'id' => $webhookLog->id,
                'provider' => $webhookLog->provider_display_name,
                'event_type' => $webhookLog->event_type_display_name,
                'status' => $webhookLog->status_display,
                'transaction_reference' => $webhookLog->transaction?->reference,
                'payload' => $webhookLog->sanitized_payload,
                'headers' => $webhookLog->sanitized_headers,
                'signature' => $webhookLog->signature ? 'Present' : 'Missing',
                'retry_count' => $webhookLog->retry_count,
                'error_message' => $webhookLog->error_message,
                'created_at' => $webhookLog->created_at->toISOString(),
                'processed_at' => $webhookLog->processed_at?->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving webhook log details', [
                'webhook_log_id' => $webhookLog->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrieving webhook log details',
                500
            );
        }
    }

    /**
     * Retry failed webhook processing (admin endpoint).
     */
    public function retry(WebhookLog $webhookLog): JsonResponse
    {
        try {
            if (!$webhookLog->canBeRetried()) {
                return $this->errorResponse(
                    'WEBHOOK_CANNOT_BE_RETRIED',
                    'This webhook cannot be retried',
                    422
                );
            }

            $webhookLog->incrementRetryCount();

            // Queue for retry
            Queue::push(new ProcessWebhookJob($webhookLog->id));

            Log::info('Webhook retry queued', [
                'webhook_log_id' => $webhookLog->id,
                'retry_count' => $webhookLog->retry_count,
            ]);

            return $this->successResponse([
                'id' => $webhookLog->id,
                'retry_count' => $webhookLog->retry_count,
                'status' => $webhookLog->status,
            ], 'Webhook retry queued successfully');

        } catch (\Exception $e) {
            Log::error('Error retrying webhook', [
                'webhook_log_id' => $webhookLog->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error retrying webhook',
                500
            );
        }
    }

    /**
     * Bulk retry failed webhooks (admin endpoint).
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'sometimes|string',
            'hours' => 'sometimes|integer|min:1|max:72',
        ]);

        try {
            $query = WebhookLog::retryable();

            if ($request->provider) {
                $query->withProvider($request->provider);
            }

            $hours = $request->hours ?? 24;
            $query->where('created_at', '>=', now()->subHours($hours));

            $failedWebhooks = $query->get();
            $retryCount = 0;

            foreach ($failedWebhooks as $webhookLog) {
                $webhookLog->incrementRetryCount();
                Queue::push(new ProcessWebhookJob($webhookLog->id));
                $retryCount++;
            }

            Log::info('Bulk webhook retry initiated', [
                'provider' => $request->provider,
                'hours' => $hours,
                'retry_count' => $retryCount,
            ]);

            return $this->successResponse([
                'retried_count' => $retryCount,
                'provider' => $request->provider,
                'time_range_hours' => $hours,
            ], "Successfully queued {$retryCount} webhooks for retry");

        } catch (\Exception $e) {
            Log::error('Error in bulk webhook retry', [
                'error' => $e->getMessage(),
                'filters' => $request->validated(),
            ]);

            return $this->errorResponse(
                'INTERNAL_ERROR',
                'Error in bulk webhook retry',
                500
            );
        }
    }
}