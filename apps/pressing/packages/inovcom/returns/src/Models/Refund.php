<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Returns\Enums\RefundMethod;
use InovCom\Returns\Enums\RefundStatus;

class Refund extends TenantModel
{
    protected $table = 'refunds';

    protected $fillable = [
        'refund_number',
        'client_id',
        'credit_note_id',
        'return_id',
        'status',
        'method',
        'amount',
        'refund_date',
        'caisse_entry_id',
        'invoice_payment_id',
        'external_reference',
        'notes',
        'store_id',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'status' => RefundStatus::class,
        'method' => RefundMethod::class,
        'amount' => 'decimal:2',
        'refund_date' => 'date',
        'validated_at' => 'datetime',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }
}
