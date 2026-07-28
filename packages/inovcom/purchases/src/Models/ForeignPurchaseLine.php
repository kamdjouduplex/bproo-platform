<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Purchases\Services\ForeignPurchasesService;

class ForeignPurchaseLine extends TenantModel
{
    protected $fillable = [
        'foreign_purchase_order_id',
        'item_id',
        'item_name',
        'quantity',
        'unit_price_foreign',
        'unit_price_local',
        'line_total_foreign',
        'line_total_local',
        'received_quantity',
        'cancelled_quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price_foreign' => 'decimal:4',
        'unit_price_local' => 'decimal:2',
        'line_total_foreign' => 'decimal:2',
        'line_total_local' => 'decimal:2',
        'received_quantity' => 'decimal:3',
        'cancelled_quantity' => 'decimal:3',
    ];

    public function foreignPurchaseOrder()
    {
        return $this->belongsTo(ForeignPurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class);
    }

    public function receiptLines()
    {
        return $this->hasMany(ForeignReceiptLine::class);
    }

    public function getActiveQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->cancelled_quantity);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->active_quantity - (float) $this->received_quantity);
    }

    public function getFulfillmentPercentAttribute(): float
    {
        if ($this->active_quantity <= 0) {
            return 100.0;
        }

        return min(100, round(((float) $this->received_quantity / $this->active_quantity) * 100, 1));
    }
}
