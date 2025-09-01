<?php

namespace Cashkdiopen\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

/**
 * Cashkdiopen Webhook Log Model
 *
 * @property string $id
 * @property string|null $transaction_id
 * @property string $provider
 * @property string $event_type
 * @property array $payload
 * @property array $headers
 * @property string|null $signature
 * @property string $status
 * @property string|null $error_message
 * @property int $retry_count
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cashkdiopen_webhook_logs';

    protected $fillable = [
        'transaction_id',
        'provider',
        'event_type',
        'payload',
        'headers',
        'signature',
        'status',
        'error_message',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'retry_count' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_IGNORED = 'ignored';

    /**
     * Event type constants.
     */
    const EVENT_PAYMENT_CREATED = 'payment.created';
    const EVENT_PAYMENT_PROCESSING = 'payment.processing';
    const EVENT_PAYMENT_SUCCEEDED = 'payment.succeeded';
    const EVENT_PAYMENT_FAILED = 'payment.failed';
    const EVENT_PAYMENT_CANCELED = 'payment.canceled';
    const EVENT_PAYMENT_EXPIRED = 'payment.expired';

    /**
     * Maximum retry attempts.
     */
    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $webhookLog) {
            if (empty($webhookLog->status)) {
                $webhookLog->status = self::STATUS_PENDING;
            }

            if (is_null($webhookLog->retry_count)) {
                $webhookLog->retry_count = 0;
            }
        });
    }

    /**
     * Get the transaction associated with this webhook.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Check if the webhook processing was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the webhook processing failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the webhook is still being processed.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the webhook is pending processing.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the webhook was ignored.
     */
    public function wasIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }

    /**
     * Check if the webhook can be retried.
     */
    public function canBeRetried(): bool
    {
        return $this->hasFailed() && $this->retry_count < self::MAX_RETRY_ATTEMPTS;
    }

    /**
     * Mark the webhook as processing.
     */
    public function markAsProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        return $this;
    }

    /**
     * Mark the webhook as successfully processed.
     */
    public function markAsSuccessful(): self
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'processed_at' => now(),
            'error_message' => null,
        ]);

        return $this;
    }

    /**
     * Mark the webhook as failed.
     */
    public function markAsFailed(string $errorMessage = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the webhook as ignored.
     */
    public function markAsIgnored(string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_IGNORED,
            'error_message' => $reason,
            'processed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Increment the retry count.
     */
    public function incrementRetryCount(): self
    {
        $this->increment('retry_count');
        
        // Reset status to pending for retry
        $this->update([
            'status' => self::STATUS_PENDING,
            'error_message' => null,
        ]);

        return $this;
    }

    /**
     * Get the event type display name.
     */
    public function getEventTypeDisplayNameAttribute(): string
    {
        return match($this->event_type) {
            self::EVENT_PAYMENT_CREATED => 'Payment Created',
            self::EVENT_PAYMENT_PROCESSING => 'Payment Processing',
            self::EVENT_PAYMENT_SUCCEEDED => 'Payment Succeeded',
            self::EVENT_PAYMENT_FAILED => 'Payment Failed',
            self::EVENT_PAYMENT_CANCELED => 'Payment Canceled',
            self::EVENT_PAYMENT_EXPIRED => 'Payment Expired',
            default => ucfirst(str_replace(['_', '.'], [' ', ' '], $this->event_type)),
        };
    }

    /**
     * Get the provider display name.
     */
    public function getProviderDisplayNameAttribute(): string
    {
        return match($this->provider) {
            'orange_money' => 'Orange Money',
            'mtn_momo' => 'MTN Mobile Money',
            'cards' => 'Bank Cards',
            default => ucfirst(str_replace('_', ' ', $this->provider)),
        };
    }

    /**
     * Get the status display name with color class.
     */
    public function getStatusDisplayAttribute(): array
    {
        return match($this->status) {
            self::STATUS_PENDING => ['name' => 'Pending', 'class' => 'text-yellow-600'],
            self::STATUS_PROCESSING => ['name' => 'Processing', 'class' => 'text-blue-600'],
            self::STATUS_SUCCESS => ['name' => 'Success', 'class' => 'text-green-600'],
            self::STATUS_FAILED => ['name' => 'Failed', 'class' => 'text-red-600'],
            self::STATUS_IGNORED => ['name' => 'Ignored', 'class' => 'text-gray-600'],
            default => ['name' => ucfirst($this->status), 'class' => 'text-gray-600'],
        };
    }

    /**
     * Get the time elapsed since creation.
     */
    public function getTimeElapsedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the sanitized payload (remove sensitive data).
     */
    public function getSanitizedPayloadAttribute(): array
    {
        $payload = $this->payload ?? [];
        $sensitiveKeys = ['api_key', 'secret', 'token', 'password', 'pin'];

        return $this->sanitizeArray($payload, $sensitiveKeys);
    }

    /**
     * Get the sanitized headers (remove sensitive data).
     */
    public function getSanitizedHeadersAttribute(): array
    {
        $headers = $this->headers ?? [];
        $sensitiveKeys = ['authorization', 'x-api-key', 'x-secret'];

        return $this->sanitizeArray($headers, $sensitiveKeys);
    }

    /**
     * Sanitize an array by masking sensitive keys.
     */
    protected function sanitizeArray(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            if (in_array($lowerKey, $sensitiveKeys)) {
                $data[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value, $sensitiveKeys);
            }
        }

        return $data;
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by provider.
     */
    public function scopeWithProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for filtering by event type.
     */
    public function scopeWithEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for successful webhooks.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending webhooks.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for retryable webhooks.
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', self::STATUS_FAILED)
                    ->where('retry_count', '<', self::MAX_RETRY_ATTEMPTS);
    }

    /**
     * Scope for recent webhooks.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}