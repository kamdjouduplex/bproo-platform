<?php

namespace InovCom\Invoicing\Models;

use InovCom\Kernel\TenantModel;

class InvoiceTaxLine extends TenantModel
{
    protected $fillable = [
        'invoice_id',
        'tax_name',
        'tax_mode',
        'tax_rate',
        'tax_amount',
        'tax_effect',
        'sort_order',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}

