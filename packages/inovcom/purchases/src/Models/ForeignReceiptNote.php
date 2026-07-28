<?php

namespace InovCom\Purchases\Models;

use InovCom\Kernel\TenantModel;

class ForeignReceiptNote extends TenantModel
{
    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'foreign_purchase_order_id',
        'status',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function foreignPurchaseOrder()
    {
        return $this->belongsTo(ForeignPurchaseOrder::class);
    }

    public function lines()
    {
        return $this->hasMany(ForeignReceiptLine::class);
    }

    public function receiver()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'received_by');
    }
}
