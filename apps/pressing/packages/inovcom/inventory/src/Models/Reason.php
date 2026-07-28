<?php

namespace InovCom\Inventory\Models;

use InovCom\Kernel\TenantModel;

class Reason extends TenantModel
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function adjustments()
    {
        return $this->hasMany(Adjustment::class);
    }

    public function isLoss(): bool
    {
        return $this->type === 'loss';
    }

    public function isGain(): bool
    {
        return $this->type === 'gain';
    }

    public function isAdjustment(): bool
    {
        return $this->type === 'adjustment';
    }
}
