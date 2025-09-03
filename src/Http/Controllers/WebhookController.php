<?php

namespace Cashkdiopen\Payments\Http\Controllers;

use Cashkdiopen\Payments\Models\WebhookLog;
use Cashkdiopen\Payments\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends BaseController
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle Orange Money webhook.
     */
    public function handleOrangeMoney(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->processWebhook('orange-money', $request);
            
            return $this->success($result, 'Webhook processed successfully');
        } catch (\Exception $e) {
            return $this->error('Webhook processing failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle MTN Mobile Money webhook.
     */
    public function handleMtnMoMo(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->processWebhook('mtn-momo', $request);
            
            return $this->success($result, 'Webhook processed successfully');
        } catch (\Exception $e) {
            return $this->error('Webhook processing failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle bank cards webhook.
     */
    public function handleCards(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->processWebhook('cards', $request);
            
            return $this->success($result, 'Webhook processed successfully');
        } catch (\Exception $e) {
            return $this->error('Webhook processing failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Handle generic webhook (auto-detect provider).
     */
    public function handleGeneric(string $provider, Request $request): JsonResponse
    {
        try {
            $result = $this->webhookService->processWebhook($provider, $request);
            
            return $this->success($result, 'Webhook processed successfully');
        } catch (\Exception $e) {
            return $this->error('Webhook processing failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * List webhook logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string',
            'status' => 'nullable|string|in:pending,processed,failed',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $logs = WebhookLog::query()
            ->when($request->provider, fn($q, $provider) => $q->where('provider', $provider))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->success($logs);
    }

    /**
     * Show webhook log details.
     */
    public function showLog(WebhookLog $webhookLog): JsonResponse
    {
        return $this->success($webhookLog);
    }

    /**
     * Retry failed webhook processing.
     */
    public function retry(WebhookLog $webhookLog): JsonResponse
    {
        if ($webhookLog->status !== 'failed') {
            return $this->error('Only failed webhooks can be retried', null, 400);
        }

        try {
            $result = $this->webhookService->retryWebhook($webhookLog);
            
            return $this->success($result, 'Webhook retry initiated');
        } catch (\Exception $e) {
            return $this->error('Webhook retry failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Retry all failed webhooks.
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $count = $this->webhookService->retryFailedWebhooks(
                $request->provider,
                $request->limit ?? 50
            );
            
            return $this->success(['retried_count' => $count], "Retried {$count} failed webhooks");
        } catch (\Exception $e) {
            return $this->error('Bulk retry failed: ' . $e->getMessage(), null, 500);
        }
    }
}