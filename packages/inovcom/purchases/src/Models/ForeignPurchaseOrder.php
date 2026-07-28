<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Purchases\Services\ForeignPurchasesService;

class ForeignPurchaseOrder extends TenantModel
{
    protected $fillable = [
        'order_number',
        'order_date',
        'expected_date',
        'provider_id',
        'currency_code',
        'exchange_rate',
        'subtotal_foreign',
        'subtotal_local',
        'status',
        'confirmed_at',
        'notes',
        'store_id',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'confirmed_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal_foreign' => 'decimal:2',
        'subtotal_local' => 'decimal:2',
    ];

    public function setExpectedDateAttribute($value): void
    {
        $this->attributes['expected_date'] = blank($value) ? null : $value;
    }

    public function lines()
    {
        return $this->hasMany(ForeignPurchaseLine::class);
    }

    public function receipts()
    {
        return $this->hasMany(ForeignReceiptNote::class);
    }

    public function provider()
    {
        return $this->belongsTo(\InovCom\Providers\Models\Provider::class);
    }

    public function creator()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'created_by');
    }

    public function isFullyReceived(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->remaining_quantity > 0.0001) {
                return false;
            }
        }

        return $this->lines->isNotEmpty();
    }

    public function getReceptionPercentAttribute(): float
    {
        $active = 0.0;
        $received = 0.0;
        foreach ($this->lines as $line) {
            $active += $line->active_quantity;
            $received += (float) $line->received_quantity;
        }

        if ($active <= 0) {
            return $this->status === ForeignPurchasesService::STATUS_RECEIVED ? 100.0 : 0.0;
        }

        return min(100, round(($received / $active) * 100, 1));
    }

    public function canReceive(): bool
    {
        if ($this->isFullyReceived()) {
            return false;
        }

        return in_array($this->status, [
            ForeignPurchasesService::STATUS_DRAFT,
            ForeignPurchasesService::STATUS_CONFIRMED,
            ForeignPurchasesService::STATUS_PARTIAL,
        ], true) && $this->lines->isNotEmpty();
    }

    public function isEditable(): bool
    {
        return app(ForeignPurchasesService::class)->canEditOrder($this);
    }
}
