<?php

namespace InovCom\Providers\Models;

use InovCom\Kernel\TenantModel;

class PaymentTerm extends TenantModel
{
    protected $fillable = [
        'name',
        'days',
        'description',
        'is_active',
    ];

    protected $casts = [
        'days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function providers()
    {
        return $this->hasMany(Provider::class);
    }
}
