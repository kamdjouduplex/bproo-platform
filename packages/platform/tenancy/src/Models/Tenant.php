<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'code',
        'db_name',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
        'is_active',
        'type',
        'contact_key_first_name',
        'contact_key_last_name',
        'contact_key_phone',
        'country',
        'city',
        'contact_key_address',
        'metadata',
        'provisioning_status',
        'provisioning_error',
        'provisioned_at',
        'multi_store_enabled',
        'multi_store_enabled_at',
        'multi_store_setup_status',
        'multi_store_setup_error',
        'balance',
        'balance_currency',
        'metrics_cached_at',
        'users_count',
        'modules_enabled_count',
        'last_tenant_activity_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'db_password' => 'encrypted',
        'provisioned_at' => 'datetime',
        'multi_store_enabled' => 'boolean',
        'multi_store_enabled_at' => 'datetime',
        'balance' => 'decimal:2',
        'metrics_cached_at' => 'datetime',
        'last_tenant_activity_at' => 'datetime',
    ];

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->withPivot(['enabled'])
            ->withTimestamps();
    }

    public function settings()
    {
        return $this->hasMany(TenantSetting::class);
    }

    /**
     * Subscriptions (current and history). One active subscription per tenant at a time.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->orderByDesc('id');
    }

    /**
     * Payments recorded for this tenant (applied to subscription or to balance).
     */
    public function payments()
    {
        return $this->hasMany(TenantPayment::class, 'tenant_id')->orderByDesc('paid_at');
    }

    /**
     * Balance ledger (credits and debits).
     */
    public function balanceTransactions()
    {
        return $this->hasMany(TenantBalanceTransaction::class, 'tenant_id')->orderByDesc('created_at');
    }

    /**
     * Current or most recent subscription (for display and checks).
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->first();
    }

    /**
     * Whether the tenant has an active subscription (status=active and period not ended).
     */
    public function hasActiveSubscription(): bool
    {
        $sub = $this->currentSubscription();
        return $sub && $sub->isActive() && !$sub->isPeriodOver();
    }

    public function hasModule(string $key): bool
    {
        return $this->modules()
            ->where('modules.key', $key)
            ->where('tenant_modules.enabled', true)
            ->exists();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings()
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Resolve product-app type (erp, pressing, bat). Maps legacy activity labels.
     */
    public function getTypeAttribute($value): string
    {
        return static::normalizeType($value);
    }

    /**
     * Human-readable product app label.
     */
    public function getTypeLabelAttribute(): string
    {
        $types = config('tenant_types.types', []);
        $resolved = static::normalizeType($this->attributes['type'] ?? null);

        return $types[$resolved]['label'] ?? $resolved;
    }

    /**
     * Login URL for this tenant in its product app.
     */
    public function getAppLoginUrlAttribute(): ?string
    {
        $types = config('tenant_types.types', []);
        $resolved = static::normalizeType($this->attributes['type'] ?? null);
        $cfg = $types[$resolved] ?? null;
        if (!$cfg) {
            return null;
        }

        $base = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $path = (string) ($cfg['login_path'] ?? '/app/login');
        if ($base === '') {
            return null;
        }

        return $base . $path . '?tenant=' . urlencode((string) $this->code);
    }

    public function supportsMultiStore(): bool
    {
        $types = config('tenant_types.types', []);
        $resolved = static::normalizeType($this->attributes['type'] ?? null);

        return (bool) ($types[$resolved]['supports_multi_store'] ?? false);
    }

    public static function normalizeType(mixed $value): string
    {
        $raw = is_string($value) && $value !== ''
            ? $value
            : (string) config('tenant_types.default', 'erp');

        $aliases = config('tenant_types.legacy_aliases', []);
        if (isset($aliases[$raw])) {
            $raw = $aliases[$raw];
        }

        $types = array_keys(config('tenant_types.types', []));
        if ($types !== [] && ! in_array($raw, $types, true)) {
            return (string) config('tenant_types.default', 'erp');
        }

        return $raw;
    }

    public function databaseConfig(): array
    {
        $username = $this->db_username ?: env('DB_USERNAME');
        $password = $this->db_password ?: env('DB_PASSWORD');
        $host = $this->db_host ?: env('DB_HOST', '127.0.0.1');
        $port = $this->db_port ?: env('DB_PORT', '5432');

        return [
            'driver' => config('inovcom.tenant.database_driver', 'pgsql'),
            'host' => $host,
            'port' => $port,
            'database' => $this->db_name,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ];
    }

    /**
     * Check if tenant is provisioned
     */
    public function isProvisioned(): bool
    {
        return $this->provisioning_status === 'completed';
    }

    /**
     * Check if tenant is provisioning
     */
    public function isProvisioning(): bool
    {
        return $this->provisioning_status === 'provisioning';
    }

    /**
     * Check if tenant provisioning failed
     */
    public function hasProvisioningFailed(): bool
    {
        return $this->provisioning_status === 'failed';
    }

    /**
     * Get provisioning status badge color
     */
    public function getProvisioningStatusColor(): string
    {
        return match($this->provisioning_status) {
            'completed' => 'success',
            'provisioning' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }
}
