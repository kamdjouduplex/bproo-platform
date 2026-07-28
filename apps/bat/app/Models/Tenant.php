<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    const PLANS = ['free', 'starter', 'pro', 'enterprise'];

    protected $fillable = [
        'name',
        'code',
        'db_name',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
        'is_active',
        'metadata',
        'provisioning_status',
        'provisioning_error',
        'provisioned_at',
        'plan',
        'plan_started_at',
        'plan_expires_at',
        'trial_ends_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'metadata'        => 'array',
        'db_password'     => 'encrypted',
        'provisioned_at'  => 'datetime',
        'plan_started_at' => 'datetime',
        'plan_expires_at' => 'datetime',
        'trial_ends_at'   => 'datetime',
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

    public function planLabel(): string
    {
        return match($this->plan ?? 'free') {
            'starter'    => 'Starter',
            'pro'        => 'Pro',
            'enterprise' => 'Enterprise',
            default      => 'Gratuit',
        };
    }

    public function isTrialing(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->plan_expires_at !== null && $this->plan_expires_at->isPast();
    }

    public function planBadgeClass(): string
    {
        return match($this->plan ?? 'free') {
            'starter'    => 'badge-starter',
            'pro'        => 'badge-pro',
            'enterprise' => 'badge-enterprise',
            default      => 'badge-free',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'code';
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
