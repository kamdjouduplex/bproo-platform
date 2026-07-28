<?php

namespace InovCom\Prescriptions\Models;

use InovCom\Kernel\TenantModel;

class PrescriptionLine extends TenantModel
{
    protected $fillable = [
        'prescription_id',
        'item_id',
        'quantity',
        'quantity_dispensed',
        'instructions',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_dispensed' => 'decimal:3',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_dispensed);
    }
}
