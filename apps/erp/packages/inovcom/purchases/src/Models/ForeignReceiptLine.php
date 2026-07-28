<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;

class ForeignReceiptLine extends TenantModel
{
    protected $fillable = [
        'foreign_receipt_note_id',
        'foreign_purchase_line_id',
        'quantity_received',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
    ];

    public function foreignReceiptNote()
    {
        return $this->belongsTo(ForeignReceiptNote::class);
    }

    public function foreignPurchaseLine()
    {
        return $this->belongsTo(ForeignPurchaseLine::class);
    }
}
