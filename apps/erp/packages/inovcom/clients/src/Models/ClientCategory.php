<?php

namespace InovCom\Clients\Models;

use InovCom\Kernel\TenantModel;

class ClientCategory extends TenantModel
{
    protected $table = 'client_categories';

    protected $fillable = [
        'name',
        'code',
        'default_discount_rate',
        'default_price_tier',
        'is_active',
    ];

    protected $casts = [
        'default_discount_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class, 'category_id');
    }
}
