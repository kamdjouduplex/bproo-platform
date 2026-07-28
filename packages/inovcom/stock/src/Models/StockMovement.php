<?php

namespace InovCom\Stock\Models;

use InovCom\Kernel\TenantModel;

class StockMovement extends TenantModel
{
    protected $fillable = [
        'item_id',
        'store_id',
        'type',
        'reference_type',
        'reference_id',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_before' => 'decimal:3',
        'quantity_after' => 'decimal:3',
    ];

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class);
    }

    public function creator()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'created_by');
    }

    public function reference()
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }
}
