<?php

namespace Cashkdiopen\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'cashkdiopen_payments';

    protected $fillable = [
        'reference',
        'external_id',
        'provider',
        'currency',
        'amount',
        'phone',
        'email',
        'description',
        'callback_url',
        'return_url',
        'status',
        'provider_reference',
        'provider_data',
        'metadata',
        'expires_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_data' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the transaction for this payment.
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * Get the webhook logs for this payment.
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if payment is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
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
     * Scope to filter by currency.
     */
    public function scopeCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }
}