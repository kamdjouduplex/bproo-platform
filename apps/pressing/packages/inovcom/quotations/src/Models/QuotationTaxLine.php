<?php

namespace InovCom\Quotations\Models;

use InovCom\Kernel\TenantModel;

class QuotationTaxLine extends TenantModel
{
    protected $fillable = [
        'quotation_id',
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

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}

