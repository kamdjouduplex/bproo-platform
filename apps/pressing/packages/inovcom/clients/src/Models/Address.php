<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class Address extends TenantModel
{
    protected $fillable = [
        'client_id',
        'type',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
