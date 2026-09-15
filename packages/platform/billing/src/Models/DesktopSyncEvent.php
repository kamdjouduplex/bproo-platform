<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesktopSyncEvent extends Model
{
    protected $fillable = [
        'event_id',
        'desktop_install_id',
        'tenant_id',
        'batch_id',
        'product_key',
        'type',
        'schema_version',
        'occurred_at',
        'received_at',
        'payload',
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'payload' => 'array',
    ];

    public function install(): BelongsTo
    {
        return $this->belongsTo(DesktopInstall::class, 'desktop_install_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DesktopSyncBatch::class, 'batch_id');
    }
}
