<?php

namespace Cashkdiopen\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Cashkdiopen Transaction Model
 *
 * @property string $id
 * @property string $reference
 * @property string $provider
 * @property int $amount
 * @property string $currency
 * @property string|null $customer_phone
 * @property string $description
 * @property string $status
 * @property string|null $provider_reference
 * @property array|null $provider_response
 * @property string $callback_url
 * @property string $return_url
 * @property array|null $metadata
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'cashkdiopen_transactions';

    protected $fillable = [
        'reference',
        'provider',
        'amount',
        'currency',
        'customer_phone',
        'description',
        'status',
        'provider_reference',
        'provider_response',
        'callback_url',
        'return_url',
        'metadata',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'provider_response' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_response', // May contain sensitive data
    ];

    /**
     * Transaction status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_EXPIRED = 'expired';

    /**
     * Supported providers.
     */
    const PROVIDER_ORANGE_MONEY = 'orange_money';
    const PROVIDER_MTN_MOMO = 'mtn_momo';
    const PROVIDER_CARDS = 'cards';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $transaction) {
            // Generate unique reference if not provided
            if (empty($transaction->reference)) {
                $transaction->reference = $transaction->generateReference();
            }

            // Set default expiry time (30 minutes from now)
            if (empty($transaction->expires_at)) {
                $transaction->expires_at = now()->addMinutes(30);
            }

            // Set default status
            if (empty($transaction->status)) {
                $transaction->status = self::STATUS_PENDING;
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * Generate a unique transaction reference.
     */
    public function generateReference(): string
    {
        do {
            $reference = 'CK_' . now()->format('Ymd') . '_' . Str::upper(Str::random(8));
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Get the payments for this transaction.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the webhook logs for this transaction.
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * Get the API key that created this transaction.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Check if the transaction is in a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
            self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Check if the transaction is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the transaction has failed.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
            self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Check if the transaction is expired.
     */
    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if the transaction can be canceled.
     */
    public function canBeCanceled(): bool
    {
        return !$this->isFinal() && !$this->isExpired();
    }

    /**
     * Mark the transaction as processing.
     */
    public function markAsProcessing(string $providerReference = null): self
    {
        return $this->updateStatus(self::STATUS_PROCESSING, $providerReference);
    }

    /**
     * Mark the transaction as successful.
     */
    public function markAsSuccessful(string $providerReference = null, array $providerResponse = null): self
    {
        $this->completed_at = now();
        
        if ($providerResponse !== null) {
            $this->provider_response = $providerResponse;
        }

        return $this->updateStatus(self::STATUS_SUCCESS, $providerReference);
    }

    /**
     * Mark the transaction as failed.
     */
    public function markAsFailed(string $reason = null, array $providerResponse = null): self
    {
        $this->completed_at = now();
        
        if ($providerResponse !== null) {
            $this->provider_response = $providerResponse;
        }

        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['failure_reason'] = $reason;
            $this->metadata = $metadata;
        }

        return $this->updateStatus(self::STATUS_FAILED);
    }

    /**
     * Mark the transaction as canceled.
     */
    public function markAsCanceled(string $reason = null): self
    {
        $this->completed_at = now();

        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['cancel_reason'] = $reason;
            $this->metadata = $metadata;
        }

        return $this->updateStatus(self::STATUS_CANCELED);
    }

    /**
     * Mark the transaction as expired.
     */
    public function markAsExpired(): self
    {
        $this->completed_at = now();
        return $this->updateStatus(self::STATUS_EXPIRED);
    }

    /**
     * Update the transaction status.
     */
    protected function updateStatus(string $status, string $providerReference = null): self
    {
        // Prevent status changes if already in final state
        if ($this->isFinal() && $this->status !== $status) {
            throw new \RuntimeException("Cannot change status from {$this->status} to {$status}");
        }

        $this->status = $status;
        
        if ($providerReference !== null) {
            $this->provider_reference = $providerReference;
        }

        $this->save();

        return $this;
    }

    /**
     * Get the amount in the major currency unit.
     */
    public function getAmountAttribute($value): float
    {
        return $value / 100;
    }

    /**
     * Set the amount from the major currency unit.
     */
    public function setAmountAttribute($value): void
    {
        $this->attributes['amount'] = $value * 100;
    }

    /**
     * Get the formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        $amount = number_format($this->amount, 2);
        return "{$amount} {$this->currency}";
    }

    /**
     * Get the masked customer phone number.
     */
    public function getMaskedPhoneAttribute(): string
    {
        if (empty($this->customer_phone)) {
            return '';
        }

        $phone = $this->customer_phone;
        $length = strlen($phone);
        
        if ($length <= 4) {
            return $phone;
        }

        $start = substr($phone, 0, 4);
        $end = substr($phone, -2);
        $middle = str_repeat('*', $length - 6);

        return $start . $middle . $end;
    }

    /**
     * Get the time remaining until expiry.
     */
    public function getTimeUntilExpiryAttribute(): ?int
    {
        if ($this->isFinal()) {
            return null;
        }

        $secondsRemaining = now()->diffInSeconds($this->expires_at, false);
        return max(0, $secondsRemaining);
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
     * Scope for filtering by currency.
     */
    public function scopeWithCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope for filtering by amount range.
     */
    public function scopeWithAmountBetween($query, int $minAmount, int $maxAmount)
    {
        return $query->whereBetween('amount', [$minAmount * 100, $maxAmount * 100]);
    }

    /**
     * Scope for filtering by date range.
     */
    public function scopeWithDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for expired transactions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                    ->whereNotIn('status', [
                        self::STATUS_SUCCESS,
                        self::STATUS_FAILED,
                        self::STATUS_CANCELED,
                        self::STATUS_EXPIRED,
                    ]);
    }

    /**
     * Scope for successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
            self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Scope for pending transactions.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ]);
    }
}