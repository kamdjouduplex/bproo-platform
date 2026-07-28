<?php

namespace InovCom\Quotations\Models;

use InovCom\Kernel\TenantModel;

class QuotationLine extends TenantModel
{
    protected $fillable = [
        'quotation_id',
        'item_id',
        'item_name',
        'item_sku',
        'line_number',
        'quantity',
        'unit_price',
        'unit_cost',
        'markup_coefficient',
        'line_discount',
        'line_discount_mode',
        'line_discount_input',
        'unit_price_net',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'markup_coefficient' => 'decimal:4',
        'line_discount' => 'decimal:2',
        'line_discount_input' => 'decimal:6',
        'unit_price_net' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_number' => 'integer',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
