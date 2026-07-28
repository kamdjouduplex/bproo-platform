<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

/**
 * Écriture du portefeuille (wallet) client. Le solde = somme signée des écritures.
 */
class CustomerCredit extends TenantModel
{
    protected $table = 'customer_credits';

    protected $fillable = [
        'client_id',
        'direction',
        'amount',
        'source_type',
        'source_id',
        'balance_after',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class, 'client_id');
    }

    public function signedAmount(): float
    {
        return $this->direction === 'credit'
            ? (float) $this->amount
            : -(float) $this->amount;
    }
}
