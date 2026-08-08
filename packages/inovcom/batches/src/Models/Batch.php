<?php

namespace InovCom\Batches\Models;

use InovCom\Kernel\TenantModel;

class Batch extends TenantModel
{
    protected $fillable = [
        'item_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'received_at',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'expiry_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class, 'item_id');
    }

    public function movements()
    {
        return $this->hasMany(BatchMovement::class)->orderBy('id');
    }

    public function isExpired(): bool
    {
        // Sellable through the expiry calendar day; blocked from the next day.
        return $this->expiry_date->lt(now()->startOfDay());
    }

    /**
     * Alert window for pharmacy dashboards.
     *
     * @return 'expired'|'d30'|'d90'|'d180'|'ok'
     */
    public function expiryAlertLevel(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        $days = now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false);
        if ($days <= 30) {
            return 'd30';
        }
        if ($days <= 90) {
            return 'd90';
        }
        if ($days <= 180) {
            return 'd180';
        }

        return 'ok';
    }
}
