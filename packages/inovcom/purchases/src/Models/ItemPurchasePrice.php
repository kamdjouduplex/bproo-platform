<?php

namespace InovCom\Purchases\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;
use InovCom\Providers\Models\Provider;

class ItemPurchasePrice extends TenantModel
{
    protected $fillable = [
        'item_id',
        'provider_id',
        'purchase_order_id',
        'purchase_line_id',
        'unit_price',
        'quantity',
        'recorded_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'recorded_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseLine()
    {
        return $this->belongsTo(PurchaseLine::class);
    }
}
