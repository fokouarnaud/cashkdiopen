<?php

namespace Cashkdiopen\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use HasFactory;

    protected $table = 'cashkdiopen_webhook_logs';

    protected $fillable = [
        'payment_id',
        'provider',
        'event_type',
        'payload',
        'headers',
        'status',
        'processed_at',
        'error_message',
        'retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /**
     * Get the payment that owns the webhook log.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Check if webhook is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if webhook is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if webhook is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if webhook can be retried.
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 5;
    }

    /**
     * Increment retry count.
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
        $this->update([
            'next_retry_at' => now()->addMinutes(5 * $this->retry_count),
        ]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter failed webhooks ready for retry.
     */
    public function scopeFailedReadyForRetry($query)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', 5)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            });
    }
}