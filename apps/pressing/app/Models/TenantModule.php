<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantModule extends Model
{
    protected $fillable = [
        'tenant_id',
        'module_id',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
