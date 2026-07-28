<?php

namespace InovCom\Debts\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class DebtPayment extends TenantModel
{
    protected $fillable = [
        'reference',
        'debt_id',
        'amount',
        'payment_date',
        'payment_method',
        'external_reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
