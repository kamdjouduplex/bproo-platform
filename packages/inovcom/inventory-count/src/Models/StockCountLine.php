<?php

namespace InovCom\Inventory\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class StockCountLine extends TenantModel
{
    protected $fillable = [
        'stock_count_id',
        'item_id',
        'expected_quantity',
        'counted_quantity',
        'difference',
        'value_difference',
        'notes',
        'counted_by',
        'counted_at',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:3',
        'counted_quantity' => 'decimal:3',
        'difference' => 'decimal:3',
        'value_difference' => 'decimal:2',
        'counted_at' => 'datetime',
    ];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function counter()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function calculateDifference(): void
    {
        if ($this->counted_quantity !== null) {
            $this->difference = $this->counted_quantity - $this->expected_quantity;
            
            // Calculate value difference based on item cost
            // Reload item if not loaded
            if (!$this->relationLoaded('item')) {
                $this->load('item');
            }
            
            if ($this->item && $this->item->cost) {
                $this->value_difference = $this->difference * $this->item->cost;
            } else {
                $this->value_difference = 0;
            }
        }
    }
}
