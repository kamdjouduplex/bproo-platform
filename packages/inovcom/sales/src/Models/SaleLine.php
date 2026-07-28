<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class SaleLine extends TenantModel
{
    protected $fillable = [
        'sale_id',
        'item_id',
        'batch_id',
        'item_name',
        'item_sku',
        'unit_id',
        'unit_name',
        'conversion_factor',
        'quantity',
        'unit_price',
        'line_total',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'conversion_factor' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class);
    }
}
