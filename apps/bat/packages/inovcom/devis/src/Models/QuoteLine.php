<?php

namespace InovCom\Devis\Models;

use InovCom\Kernel\TenantModel;

class QuoteLine extends TenantModel
{
    protected $table = 'quote_lines';

    protected $fillable = [
        'quote_id',
        'position',
        'item_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_percent',
        'cost',
        'amount',
        'line_type',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'cost'             => 'decimal:2',
        'amount'           => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (QuoteLine $line) {
            // Recalculate amount if it is zero or not explicitly set externally
            if ($line->amount == 0 && $line->quantity && $line->unit_price !== null) {
                $disc = (float) ($line->discount_percent ?? 0);
                $line->amount = round((float) $line->quantity * (float) $line->unit_price * (1 - $disc / 100), 2);
            }
        });
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class, 'item_id');
    }
}
