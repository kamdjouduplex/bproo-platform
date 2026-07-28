<?php

namespace InovCom\Items\Models;

use InovCom\Kernel\TenantModel;

class Unit extends TenantModel
{
    protected $fillable = [
        'name',
        'abbreviation',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
