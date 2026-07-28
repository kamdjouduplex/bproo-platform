<?php

namespace InovCom\Stock\Models;

use InovCom\Kernel\TenantModel;

class StockLevel extends TenantModel
{
    protected $fillable = [
        'item_id',
        'store_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
        'reorder_point',
        'max_stock',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reserved_quantity' => 'decimal:3',
        'available_quantity' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'max_stock' => 'decimal:3',
    ];

    public function item()
    {
        return $this->belongsTo(\InovCom\Items\Models\Item::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'item_id', 'item_id')
            ->when($this->store_id !== null, fn ($q) => $q->where('store_id', $this->store_id));
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_point === null) {
            return false;
        }
        return $this->available_quantity <= $this->reorder_point;
    }

    public function updateAvailableQuantity(): void
    {
        $this->available_quantity = max(0, $this->quantity - $this->reserved_quantity);
        $this->save();
    }
}
