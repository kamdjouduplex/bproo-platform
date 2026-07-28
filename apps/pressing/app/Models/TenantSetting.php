<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
