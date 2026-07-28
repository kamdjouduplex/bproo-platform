<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class CreditLimit extends TenantModel
{
    protected $fillable = [
        'client_id',
        'amount',
        'valid_from',
        'valid_until',
        'notes',
        'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function isActive(): bool
    {
        $now = now();
        return $this->valid_from <= $now && 
               ($this->valid_until === null || $this->valid_until >= $now);
    }
}
