<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class Zone extends TenantModel
{
    protected $fillable = [
        'name',
        'code',
        'region',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
