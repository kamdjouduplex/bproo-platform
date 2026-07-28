<?php

namespace InovCom\Items\Models;

use InovCom\Kernel\TenantModel;

class Brand extends TenantModel
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
