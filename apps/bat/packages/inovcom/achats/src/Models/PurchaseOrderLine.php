<?php

namespace InovCom\Achats\Models;

use InovCom\Kernel\TenantModel;

class PurchaseOrderLine extends TenantModel
{
    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'position',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'item_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderLine $line) {
            if ($line->amount == 0 && $line->quantity && $line->unit_price !== null) {
                $line->amount = round($line->quantity * $line->unit_price, 2);
            }
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class, 'item_id');
    }
}
