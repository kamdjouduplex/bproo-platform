<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DesktopInstall extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'product_key',
        'label',
        'activation_code',
        'fingerprint_hash',
        'status',
        'token_version',
        'activated_at',
        'last_heartbeat_at',
        'revoked_at',
        'app_version',
        'os',
        'last_ip',
        'meta',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'revoked_at' => 'datetime',
        'token_version' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $install) {
            if (empty($install->uuid)) {
                $install->uuid = (string) Str::uuid();
            }
            if (empty($install->activation_code)) {
                $install->activation_code = self::generateActivationCode();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function syncBatches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DesktopSyncBatch::class, 'desktop_install_id')->orderByDesc('id');
    }

    public function syncEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DesktopSyncEvent::class, 'desktop_install_id')->orderByDesc('id');
    }

    public static function generateActivationCode(): string
    {
        return strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
    }

    public static function hashFingerprint(string $fingerprint): string
    {
        return hash('sha256', trim($fingerprint));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function revoke(?string $reason = null): void
    {
        $meta = $this->meta ?? [];
        if ($reason) {
            $meta['revoke_reason'] = $reason;
        }
        if ($this->fingerprint_hash) {
            $meta['revoked_fingerprint_hash'] = $this->fingerprint_hash;
        }

        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
            'fingerprint_hash' => null,
            'token_version' => ((int) $this->token_version) + 1,
            'meta' => $meta,
        ]);
    }

    /**
     * Offline access still allowed until last_heartbeat + grace (or activated_at if never heartbeated).
     */
    public function offlineAccessExpiresAt(int $graceDays): ?\Carbon\CarbonInterface
    {
        $anchor = $this->last_heartbeat_at ?: $this->activated_at;
        if (! $anchor) {
            return null;
        }

        return $anchor->copy()->addDays(max(1, $graceDays));
    }
}
