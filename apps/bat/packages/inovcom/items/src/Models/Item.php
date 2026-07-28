<?php

namespace InovCom\Items\Models;

use InovCom\Kernel\TenantModel;

class Item extends TenantModel
{
    protected $fillable = [
        'name',
        'sku',
        'description',
        'category_id',
        'unit_id',
        'price',
        'cost',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'cost'      => 'decimal:2',
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
