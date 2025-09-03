<?php

namespace Cashkdiopen\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'cashkdiopen_transactions';

    protected $fillable = [
        'payment_id',
        'provider_transaction_id',
        'type',
        'status',
        'amount',
        'currency',
        'fees',
        'provider_fees',
        'net_amount',
        'provider_data',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'provider_fees' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'provider_data' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the payment that owns the transaction.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Check if transaction is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}