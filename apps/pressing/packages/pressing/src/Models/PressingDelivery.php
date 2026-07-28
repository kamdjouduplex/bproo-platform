<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingDelivery extends TenantModel
{
    protected $table = 'pressing_deliveries';

    public const TYPES = [
        'agence' => 'Retrait agence',
        'domicile' => 'Livraison domicile',
    ];

    public const STATUSES = [
        'pending' => 'En attente',
        'in_transit' => 'En cours',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    protected $fillable = [
        'order_id',
        'agence_id',
        'type',
        'status',
        'driver_user_id',
        'vehicle',
        'address',
        'scheduled_at',
        'delivered_at',
        'signature_path',
        'photo_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
