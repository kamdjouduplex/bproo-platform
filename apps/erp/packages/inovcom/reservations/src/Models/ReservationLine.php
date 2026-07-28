<?php

namespace InovCom\Reservations\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;

class ReservationLine extends TenantModel
{
    protected $fillable = [
        'reservation_id',
        'item_id',
        'item_name',
        'item_sku',
        'quantity',
        'quantity_cancelled',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_cancelled' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function getActiveQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_cancelled);
    }
}
