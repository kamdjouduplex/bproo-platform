<?php

namespace InovCom\Items\Models;

use InovCom\Kernel\TenantModel;

class ItemUnitPrice extends TenantModel
{
    protected $fillable = [
        'item_id',
        'unit_id',
        'conversion_factor',
        'price',
        'cost',
        'is_default',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
