<?php

namespace InovCom\Inventory\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Adjustment extends TenantModel
{
    protected $fillable = [
        'reference',
        'stock_count_id',
        'item_id',
        'reason_id',
        'quantity',
        'value',
        'notes',
        'status',
        'created_by',
        'applied_by',
        'applied_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'value' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function reason()
    {
        return $this->belongsTo(Reason::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applier()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isLoss(): bool
    {
        return $this->quantity < 0;
    }

    public function isGain(): bool
    {
        return $this->quantity > 0;
    }
}
