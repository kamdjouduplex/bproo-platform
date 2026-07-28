<?php

namespace InovCom\Returns\Models;

use InovCom\Kernel\TenantModel;

class ReturnReason extends TenantModel
{
    protected $table = 'return_reasons';

    protected $fillable = [
        'code',
        'label',
        'category',
        'requires_inspection',
        'restock_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'requires_inspection' => 'boolean',
        'restock_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
