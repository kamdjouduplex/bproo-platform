<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;

class PurchaseLine extends TenantModel
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'item_name',
        'quantity',
        'unit_price',
        'line_total',
        'received_quantity',
        'cancelled_quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'received_quantity' => 'decimal:3',
        'cancelled_quantity' => 'decimal:3',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class);
    }

    public function receiptLines()
    {
        return $this->hasMany(ReceiptLine::class);
    }

    /** Quantité encore commandée (hors annulations). */
    public function getActiveQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->cancelled_quantity);
    }

    /** Quantité restant à réceptionner. */
    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->active_quantity - (float) $this->received_quantity);
    }

    /** Partie reçue pouvant être annulée avec retrait stock. */
    public function getRemainingToCancelFromReceivedAttribute(): float
    {
        return max(0, (float) $this->received_quantity);
    }

    public function getFulfillmentPercentAttribute(): float
    {
        if ($this->active_quantity <= 0) {
            return 100.0;
        }

        return min(100, round(((float) $this->received_quantity / $this->active_quantity) * 100, 1));
    }
}
