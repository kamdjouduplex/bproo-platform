<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantCurrency extends Model
{
    protected $table = 'tenant_currencies';

    protected $fillable = [
        'tenant_id',
        'currency_code',
        'is_default',
        'is_enabled',
        'exchange_rate_to_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'exchange_rate_to_default' => 'decimal:6',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function currency()
    {
        return $this->belongsTo(PlatformCurrency::class, 'currency_code', 'code');
    }
}
