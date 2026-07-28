<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class SaleReturnRefund extends TenantModel
{
    protected $fillable = [
        'sale_return_id',
        'payment_id',
        'method',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function getMethodLabelAttribute(): string
    {
        return Payment::METHOD_LABELS[$this->method] ?? ucfirst($this->method);
    }
}
