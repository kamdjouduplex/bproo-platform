<?php

namespace InovCom\Batches\Models;

use InovCom\Kernel\TenantModel;

class BatchMovement extends TenantModel
{
    protected $fillable = [
        'batch_id',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_before' => 'decimal:3',
        'quantity_after' => 'decimal:3',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
