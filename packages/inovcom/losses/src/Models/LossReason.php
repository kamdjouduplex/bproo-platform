<?php

namespace InovCom\Losses\Models;

use InovCom\Kernel\TenantModel;

class LossReason extends TenantModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lossRecords()
    {
        return $this->hasMany(LossRecord::class);
    }
}
