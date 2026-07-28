<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Purchases\Services\PurchasesService;

class PurchaseOrder extends TenantModel
{
    protected $fillable = [
        'order_number',
        'order_date',
        'expected_date',
        'provider_id',
        'status',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'subtotal',
        'total',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function receipts()
    {
        return $this->hasMany(ReceiptNote::class);
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

    public function isFullyCancelled(): bool
    {
        if ($this->lines->isEmpty()) {
            return false;
        }

        foreach ($this->lines as $line) {
            $open = (float) $line->quantity - (float) $line->cancelled_quantity;
            if ($open > 0.0001) {
                return false;
            }
        }

        return true;
    }

    public function getTotalReceivedAttribute(): float
    {
        return (float) $this->lines()->sum('received_quantity');
    }

    public function getTotalActiveQuantityAttribute(): float
    {
        return (float) $this->lines->sum(fn ($line) => $line->active_quantity);
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
            return $this->status === PurchasesService::STATUS_RECEIVED ? 100.0 : 0.0;
        }

        return min(100, round(($received / $active) * 100, 1));
    }

    public function isEditable(): bool
    {
        return app(PurchasesService::class)->canEditOrder($this);
    }

    public function canReceive(): bool
    {
        if ($this->status === PurchasesService::STATUS_CANCELLED || $this->isFullyCancelled()) {
            return false;
        }

        if ($this->isFullyReceived()) {
            return false;
        }

        return in_array($this->status, [
            PurchasesService::STATUS_DRAFT,
            PurchasesService::STATUS_CONFIRMED,
            PurchasesService::STATUS_PARTIAL,
            PurchasesService::STATUS_SENT_LEGACY,
        ], true) && $this->lines->isNotEmpty();
    }

    public function canCancel(): bool
    {
        return $this->status !== PurchasesService::STATUS_CANCELLED
            && !$this->isFullyCancelled();
    }
}
