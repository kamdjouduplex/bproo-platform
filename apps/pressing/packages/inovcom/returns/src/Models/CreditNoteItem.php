<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class CreditNoteItem extends TenantModel
{
    protected $table = 'credit_note_items';

    protected $fillable = [
        'credit_note_id',
        'return_item_id',
        'item_id',
        'item_name',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }
}
