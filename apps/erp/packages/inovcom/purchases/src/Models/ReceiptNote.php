<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;

class ReceiptNote extends TenantModel
{
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'purchase_order_id',
        'status',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines()
    {
        return $this->hasMany(ReceiptLine::class);
    }

    public function receiver()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'received_by');
    }
}
