<?php

namespace Cashkdiopen\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

/**
 * Cashkdiopen Payment Model
 *
 * Represents individual payment attempts within a transaction.
 * A transaction can have multiple payment attempts.
 *
 * @property string $id
 * @property string $transaction_id
 * @property string $payment_method
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property string|null $provider_payment_id
 * @property array|null $provider_data
 * @property array|null $metadata
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cashkdiopen_payments';

    protected $fillable = [
        'transaction_id',
        'payment_method',
        'amount',
        'currency',
        'status',
        'provider_payment_id',
        'provider_data',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'provider_data' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_data', // May contain sensitive information
    ];

    /**
     * Payment status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';

    /**
     * Payment method constants.
     */
    const METHOD_ORANGE_MONEY = 'orange_money';
    const METHOD_MTN_MOMO = 'mtn_momo';
    const METHOD_VISA = 'visa';
    const METHOD_MASTERCARD = 'mastercard';
    const METHOD_AMEX = 'amex';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $payment) {
            if (empty($payment->status)) {
                $payment->status = self::STATUS_PENDING;
            }
        });
    }

    /**
     * Get the transaction that owns this payment.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Check if the payment is in a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
        ]);
    }

    /**
     * Check if the payment is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the payment has failed.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
        ]);
    }

    /**
     * Mark the payment as processing.
     */
    public function markAsProcessing(string $providerPaymentId = null): self
    {
        return $this->updateStatus(self::STATUS_PROCESSING, $providerPaymentId);
    }

    /**
     * Mark the payment as successful.
     */
    public function markAsSuccessful(string $providerPaymentId = null, array $providerData = null): self
    {
        $this->processed_at = now();
        
        if ($providerData !== null) {
            $this->provider_data = $providerData;
        }

        return $this->updateStatus(self::STATUS_SUCCESS, $providerPaymentId);
    }

    /**
     * Mark the payment as failed.
     */
    public function markAsFailed(string $reason = null, array $providerData = null): self
    {
        $this->processed_at = now();
        
        if ($providerData !== null) {
            $this->provider_data = $providerData;
        }

        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['failure_reason'] = $reason;
            $this->metadata = $metadata;
        }

        return $this->updateStatus(self::STATUS_FAILED);
    }

    /**
     * Mark the payment as canceled.
     */
    public function markAsCanceled(string $reason = null): self
    {
        $this->processed_at = now();

        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['cancel_reason'] = $reason;
            $this->metadata = $metadata;
        }

        return $this->updateStatus(self::STATUS_CANCELED);
    }

    /**
     * Update the payment status.
     */
    protected function updateStatus(string $status, string $providerPaymentId = null): self
    {
        // Prevent status changes if already in final state
        if ($this->isFinal() && $this->status !== $status) {
            throw new \RuntimeException("Cannot change status from {$this->status} to {$status}");
        }

        $this->status = $status;
        
        if ($providerPaymentId !== null) {
            $this->provider_payment_id = $providerPaymentId;
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
     * Get the payment method display name.
     */
    public function getMethodDisplayNameAttribute(): string
    {
        return match($this->payment_method) {
            self::METHOD_ORANGE_MONEY => 'Orange Money',
            self::METHOD_MTN_MOMO => 'MTN Mobile Money',
            self::METHOD_VISA => 'Visa',
            self::METHOD_MASTERCARD => 'Mastercard',
            self::METHOD_AMEX => 'American Express',
            default => ucfirst(str_replace('_', ' ', $this->payment_method)),
        };
    }

    /**
     * Check if this is a mobile money payment.
     */
    public function isMobileMoney(): bool
    {
        return in_array($this->payment_method, [
            self::METHOD_ORANGE_MONEY,
            self::METHOD_MTN_MOMO,
        ]);
    }

    /**
     * Check if this is a card payment.
     */
    public function isCard(): bool
    {
        return in_array($this->payment_method, [
            self::METHOD_VISA,
            self::METHOD_MASTERCARD,
            self::METHOD_AMEX,
        ]);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by payment method.
     */
    public function scopeWithMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope for successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [
            self::STATUS_FAILED,
            self::STATUS_CANCELED,
        ]);
    }

    /**
     * Scope for pending payments.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ]);
    }

    /**
     * Scope for mobile money payments.
     */
    public function scopeMobileMoney($query)
    {
        return $query->whereIn('payment_method', [
            self::METHOD_ORANGE_MONEY,
            self::METHOD_MTN_MOMO,
        ]);
    }

    /**
     * Scope for card payments.
     */
    public function scopeCard($query)
    {
        return $query->whereIn('payment_method', [
            self::METHOD_VISA,
            self::METHOD_MASTERCARD,
            self::METHOD_AMEX,
        ]);
    }
}