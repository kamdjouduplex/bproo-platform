<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Returns\Enums\ItemCondition;

class ReturnItem extends TenantModel
{
    protected $table = 'return_items';

    protected $fillable = [
        'return_id',
        'source_line_id',
        'item_id',
        'item_name',
        'item_sku',
        'quantity',
        'unit_price',
        'line_discount',
        'tax_rate',
        'line_total',
        'reason_id',
        'condition',
        'restock',
        'restocked_quantity',
    ];

    protected $casts = [
        'condition' => ItemCondition::class,
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
        'restock' => 'boolean',
        'restocked_quantity' => 'decimal:3',
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function reason()
    {
        return $this->belongsTo(ReturnReason::class, 'reason_id');
    }
}
