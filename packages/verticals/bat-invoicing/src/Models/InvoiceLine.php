<?php

namespace InovCom\Facturation\Models;

use InovCom\Kernel\TenantModel;

class InvoiceLine extends TenantModel
{
    protected $table = 'invoice_lines';

    protected $fillable = [
        'invoice_id',
        'position',
        'description',
        'quantity',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (InvoiceLine $line) {
            if ($line->amount == 0 && $line->quantity && $line->unit_price !== null) {
                $line->amount = round($line->quantity * $line->unit_price, 2);
            }
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class, 'item_id');
    }
}
