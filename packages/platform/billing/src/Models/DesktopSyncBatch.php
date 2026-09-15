<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DesktopSyncBatch extends Model
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'desktop_install_id',
        'tenant_id',
        'product_key',
        'event_count',
        'accepted_count',
        'duplicate_count',
        'status',
        'received_at',
        'client_app_version',
        'meta',
    ];

    protected $casts = [
        'event_count' => 'integer',
        'accepted_count' => 'integer',
        'duplicate_count' => 'integer',
        'received_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $batch) {
            if (empty($batch->uuid)) {
                $batch->uuid = (string) Str::uuid();
            }
        });
    }

    public function install(): BelongsTo
    {
        return $this->belongsTo(DesktopInstall::class, 'desktop_install_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DesktopSyncEvent::class, 'batch_id');
    }
}
