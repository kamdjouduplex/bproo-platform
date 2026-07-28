<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class SaleReturnLine extends TenantModel
{
    protected $fillable = [
        'sale_return_id',
        'sale_line_id',
        'item_id',
        'batch_id',
        'quantity',
        'quantity_base',
        'unit_price',
        'line_refund',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_base' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_refund' => 'decimal:2',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleLine()
    {
        return $this->belongsTo(SaleLine::class);
    }
}
