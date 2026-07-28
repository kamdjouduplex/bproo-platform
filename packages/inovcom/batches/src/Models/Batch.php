<?php

namespace InovCom\Batches\Models;

use InovCom\Kernel\TenantModel;

class Batch extends TenantModel
{
    protected $fillable = [
        'item_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'received_at',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'expiry_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class, 'item_id');
    }

    public function movements()
    {
        return $this->hasMany(BatchMovement::class)->orderBy('id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }
}
