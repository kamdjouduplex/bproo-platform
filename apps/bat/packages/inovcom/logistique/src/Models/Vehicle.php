<?php

namespace InovCom\Logistique\Models;

use InovCom\Kernel\TenantModel;

class Vehicle extends TenantModel
{
    protected $table = 'vehicles';

    protected $fillable = ['name', 'plate_number', 'type', 'capacity_kg', 'status', 'notes'];

    protected $casts = ['capacity_kg' => 'decimal:2'];

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Delivery::class, 'vehicle_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'truck'      => 'Camion',
            'van'        => 'Fourgonnette',
            'pickup'     => 'Pick-up',
            'motorcycle' => 'Moto',
            default      => $this->type,
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'available'   => 'Disponible',
            'in_use'      => 'En mission',
            'maintenance' => 'En maintenance',
            default       => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'available'   => 'badge badge-success',
            'in_use'      => 'badge badge-warning',
            'maintenance' => 'badge badge-danger',
            default       => 'badge badge-secondary',
        };
    }

    public static function types(): array
    {
        return ['truck' => 'Camion', 'van' => 'Fourgonnette', 'pickup' => 'Pick-up', 'motorcycle' => 'Moto'];
    }

    public static function statuses(): array
    {
        return ['available' => 'Disponible', 'in_use' => 'En mission', 'maintenance' => 'En maintenance'];
    }
}
