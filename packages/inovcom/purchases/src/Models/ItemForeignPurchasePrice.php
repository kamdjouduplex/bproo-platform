<?php

namespace InovCom\Purchases\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;
use InovCom\Providers\Models\Provider;

class ItemForeignPurchasePrice extends TenantModel
{
    protected $fillable = [
        'item_id',
        'provider_id',
        'foreign_purchase_order_id',
        'foreign_purchase_line_id',
        'currency_code',
        'unit_price_foreign',
        'unit_price_local',
        'quantity',
        'recorded_at',
    ];

    protected $casts = [
        'unit_price_foreign' => 'decimal:4',
        'unit_price_local' => 'decimal:2',
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

    public function foreignPurchaseOrder()
    {
        return $this->belongsTo(ForeignPurchaseOrder::class);
    }

    public function foreignPurchaseLine()
    {
        return $this->belongsTo(ForeignPurchaseLine::class);
    }
}
