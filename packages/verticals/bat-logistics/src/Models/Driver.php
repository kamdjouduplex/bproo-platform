<?php

namespace InovCom\Logistique\Models;

use InovCom\Kernel\TenantModel;

class Driver extends TenantModel
{
    protected $table = 'drivers';

    protected $fillable = ['name', 'phone', 'email', 'license_number', 'status', 'notes'];

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'active'   => 'Actif',
            'inactive' => 'Inactif',
            default    => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'active'   => 'badge badge-success',
            'inactive' => 'badge badge-secondary',
            default    => 'badge badge-secondary',
        };
    }
}
