<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class ClientContact extends TenantModel
{
    protected $table = 'client_contacts';

    protected $fillable = [
        'client_id', 'name', 'role', 'email', 'phone', 'is_primary', 'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
