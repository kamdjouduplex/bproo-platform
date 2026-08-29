<?php

namespace InovCom\InvoicePayments\Models;

use InovCom\Kernel\TenantModel;

class InvoicePaymentWithholding extends TenantModel
{
    protected $table = 'invoice_payment_withholdings';

    protected $fillable = [
        'invoice_payment_id',
        'withholding_type_id',
        'type_code',
        'type_name',
        'base_amount',
        'rate',
        'amount',
        'account_code',
        'comment',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function type()
    {
        return $this->belongsTo(FiscalWithholdingType::class, 'withholding_type_id');
    }
}
