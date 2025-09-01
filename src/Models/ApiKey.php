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
 * Cashkdiopen API Key Model
 *
 * @property string $id
 * @property string|null $user_id
 * @property string $name
 * @property string $key_id
 * @property string $key_secret
 * @property string $environment
 * @property array|null $scopes
 * @property int $rate_limit
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ApiKey extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'cashkdiopen_api_keys';

    protected $fillable = [
        'user_id',
        'name',
        'key_id',
        'key_secret',
        'environment',
        'scopes',
        'rate_limit',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'rate_limit' => 'integer',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'key_secret', // Never expose the secret in JSON responses
    ];

    /**
     * Environment constants.
     */
    const ENVIRONMENT_SANDBOX = 'sandbox';
    const ENVIRONMENT_PRODUCTION = 'production';

    /**
     * Scope constants.
     */
    const SCOPE_PAYMENTS_CREATE = 'payments:create';
    const SCOPE_PAYMENTS_READ = 'payments:read';
    const SCOPE_PAYMENTS_CANCEL = 'payments:cancel';
    const SCOPE_WEBHOOKS_RECEIVE = 'webhooks:receive';
    const SCOPE_ADMIN_READ = 'admin:read';
    const SCOPE_ADMIN_WRITE = 'admin:write';

    /**
     * Default rate limit per hour.
     */
    const DEFAULT_RATE_LIMIT = 1000;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $apiKey) {
            // Generate key ID and secret if not provided
            if (empty($apiKey->key_id)) {
                $apiKey->key_id = $apiKey->generateKeyId();
            }

            if (empty($apiKey->key_secret)) {
                $apiKey->key_secret = $apiKey->generateKeySecret();
            }

            // Set default rate limit
            if (empty($apiKey->rate_limit)) {
                $apiKey->rate_limit = self::DEFAULT_RATE_LIMIT;
            }

            // Set default scopes for new keys
            if (empty($apiKey->scopes)) {
                $apiKey->scopes = [
                    self::SCOPE_PAYMENTS_CREATE,
                    self::SCOPE_PAYMENTS_READ,
                ];
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'key_id';
    }

    /**
     * Generate a unique key ID.
     */
    public function generateKeyId(): string
    {
        $prefix = $this->environment === self::ENVIRONMENT_PRODUCTION ? 'ck_live_' : 'ck_test_';
        
        do {
            $keyId = $prefix . Str::lower(Str::random(32));
        } while (self::where('key_id', $keyId)->exists());

        return $keyId;
    }

    /**
     * Generate a secure key secret.
     */
    public function generateKeySecret(): string
    {
        return encrypt(Str::random(64));
    }

    /**
     * Get the user that owns this API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    /**
     * Get the transactions created with this API key.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the decrypted key secret.
     */
    public function getDecryptedSecret(): string
    {
        return decrypt($this->key_secret);
    }

    /**
     * Check if the API key has a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    /**
     * Check if the API key has any of the given scopes.
     */
    public function hasAnyScope(array $scopes): bool
    {
        return !empty(array_intersect($scopes, $this->scopes ?? []));
    }

    /**
     * Check if the API key has all of the given scopes.
     */
    public function hasAllScopes(array $scopes): bool
    {
        return empty(array_diff($scopes, $this->scopes ?? []));
    }

    /**
     * Add a scope to the API key.
     */
    public function addScope(string $scope): self
    {
        $scopes = $this->scopes ?? [];
        
        if (!in_array($scope, $scopes)) {
            $scopes[] = $scope;
            $this->scopes = $scopes;
            $this->save();
        }

        return $this;
    }

    /**
     * Remove a scope from the API key.
     */
    public function removeScope(string $scope): self
    {
        $scopes = $this->scopes ?? [];
        
        if (($key = array_search($scope, $scopes)) !== false) {
            unset($scopes[$key]);
            $this->scopes = array_values($scopes);
            $this->save();
        }

        return $this;
    }

    /**
     * Set the scopes for the API key.
     */
    public function setScopes(array $scopes): self
    {
        $this->scopes = array_values(array_unique($scopes));
        $this->save();

        return $this;
    }

    /**
     * Update the last used timestamp.
     */
    public function updateLastUsed(): self
    {
        $this->last_used_at = now();
        $this->save();

        return $this;
    }

    /**
     * Check if the API key is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    /**
     * Check if the API key is active.
     */
    public function isActive(): bool
    {
        return !$this->isExpired() && !$this->trashed();
    }

    /**
     * Check if the API key is for production environment.
     */
    public function isProduction(): bool
    {
        return $this->environment === self::ENVIRONMENT_PRODUCTION;
    }

    /**
     * Check if the API key is for sandbox environment.
     */
    public function isSandbox(): bool
    {
        return $this->environment === self::ENVIRONMENT_SANDBOX;
    }

    /**
     * Get the masked key ID for display.
     */
    public function getMaskedKeyIdAttribute(): string
    {
        $keyId = $this->key_id;
        $parts = explode('_', $keyId);
        
        if (count($parts) >= 3) {
            $prefix = $parts[0] . '_' . $parts[1] . '_';
            $suffix = substr(end($parts), -4);
            $masked = $prefix . str_repeat('*', 20) . $suffix;
            return $masked;
        }

        return $keyId;
    }

    /**
     * Get the days until expiry.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Get all available scopes.
     */
    public static function getAvailableScopes(): array
    {
        return [
            self::SCOPE_PAYMENTS_CREATE => 'Create payments',
            self::SCOPE_PAYMENTS_READ => 'Read payment information',
            self::SCOPE_PAYMENTS_CANCEL => 'Cancel payments',
            self::SCOPE_WEBHOOKS_RECEIVE => 'Receive webhook notifications',
            self::SCOPE_ADMIN_READ => 'Read admin data',
            self::SCOPE_ADMIN_WRITE => 'Modify admin data',
        ];
    }

    /**
     * Get the scope descriptions.
     */
    public function getScopeDescriptionsAttribute(): array
    {
        $availableScopes = self::getAvailableScopes();
        $descriptions = [];

        foreach ($this->scopes ?? [] as $scope) {
            $descriptions[$scope] = $availableScopes[$scope] ?? $scope;
        }

        return $descriptions;
    }

    /**
     * Scope for filtering by environment.
     */
    public function scopeForEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Scope for production keys.
     */
    public function scopeProduction($query)
    {
        return $query->where('environment', self::ENVIRONMENT_PRODUCTION);
    }

    /**
     * Scope for sandbox keys.
     */
    public function scopeSandbox($query)
    {
        return $query->where('environment', self::ENVIRONMENT_SANDBOX);
    }

    /**
     * Scope for active keys.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for expired keys.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for keys that haven't been used recently.
     */
    public function scopeUnused($query, int $days = 30)
    {
        return $query->where(function ($q) use ($days) {
            $q->whereNull('last_used_at')
              ->orWhere('last_used_at', '<', now()->subDays($days));
        });
    }

    /**
     * Scope for keys with a specific scope.
     */
    public function scopeWithScope($query, string $scope)
    {
        return $query->whereJsonContains('scopes', $scope);
    }
}