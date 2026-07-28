<?php

namespace InovCom\Stock\Models;

use InovCom\Kernel\TenantModel;

class Warehouse extends TenantModel
{
    protected $table = 'warehouses';

    protected $fillable = ['name', 'location', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function movements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}
