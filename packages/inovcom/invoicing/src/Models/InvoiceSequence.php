<?php

namespace InovCom\Invoicing\Models;

use InovCom\Kernel\TenantModel;

class InvoiceSequence extends TenantModel
{
    protected $fillable = [
        'declaration_type',
        'year',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];
}
