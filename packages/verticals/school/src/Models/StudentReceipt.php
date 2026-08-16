<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class StudentReceipt extends TenantModel
{
    protected $table = 'school_receipts';

    protected $fillable = [
        'payment_id',
        'receipt_number',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(SchoolPayment::class, 'payment_id');
    }
}

