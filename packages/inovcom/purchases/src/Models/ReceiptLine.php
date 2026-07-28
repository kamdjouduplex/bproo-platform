<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;

class ReceiptLine extends TenantModel
{
    protected $fillable = [
        'receipt_note_id',
        'purchase_line_id',
        'quantity_received',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
    ];

    public function receiptNote()
    {
        return $this->belongsTo(ReceiptNote::class);
    }

    public function purchaseLine()
    {
        return $this->belongsTo(PurchaseLine::class);
    }
}
