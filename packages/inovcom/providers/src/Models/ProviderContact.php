<?php

namespace InovCom\Providers\Models;

use InovCom\Kernel\TenantModel;

class ProviderContact extends TenantModel
{
    protected $table = 'provider_contacts';

    protected $fillable = [
        'provider_id',
        'name',
        'phone',
        'email',
        'position',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
