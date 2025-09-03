<?php

namespace Cashkdiopen\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'cashkdiopen_api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'permissions',
        'rate_limit',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'rate_limit' => 'integer',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'key_hash',
    ];

    /**
     * Generate a new API key.
     */
    public static function generate(string $name, array $permissions = [], ?int $rateLimit = null): array
    {
        $key = 'ckd_' . Str::random(32);
        
        $apiKey = self::create([
            'name' => $name,
            'key_hash' => hash('sha256', $key),
            'permissions' => $permissions,
            'rate_limit' => $rateLimit ?? 1000,
            'is_active' => true,
        ]);

        return [
            'model' => $apiKey,
            'key' => $key,
        ];
    }

    /**
     * Verify an API key.
     */
    public static function verify(string $key): ?self
    {
        return self::where('key_hash', hash('sha256', $key))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Check if API key has permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return true; // Full access if no permissions set
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Record usage of this API key.
     */
    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if API key is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope to filter active keys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }
}