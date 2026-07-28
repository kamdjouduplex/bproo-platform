<?php

namespace InovCom\Logistique\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Stock\Models\Warehouse;

class Delivery extends TenantModel
{
    protected $table = 'deliveries';

    protected $fillable = [
        'code', 'vehicle_id', 'driver_id', 'source_warehouse_id', 'project_id',
        'status', 'destination', 'scheduled_at', 'completed_at', 'completed_by', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'date',
        'completed_at' => 'datetime',
    ];

    public function vehicle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function driver(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function sourceWarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'delivery_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'     => 'En attente',
            'in_progress' => 'En cours',
            'completed'   => 'Livrée',
            'cancelled'   => 'Annulée',
            default       => $this->status,
        };
    }

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            'pending'     => 'badge badge-warning',
            'in_progress' => 'badge badge-info',
            'completed'   => 'badge badge-success',
            'cancelled'   => 'badge badge-danger',
            default       => 'badge badge-secondary',
        };
    }

    public static function generateCode(): string
    {
        $max = static::on('tenant')
            ->where('code', 'like', 'LIV%')
            ->pluck('code')
            ->map(fn(string $c): int => (int) substr($c, 3))
            ->filter(fn(int $n): bool => $n > 0)
            ->max();

        return 'LIV' . str_pad((string)(($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
