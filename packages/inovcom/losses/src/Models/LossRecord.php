<?php

namespace InovCom\Losses\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class LossRecord extends TenantModel
{
    protected $fillable = [
        'reference',
        'item_id',
        'loss_reason_id',
        'quantity',
        'value',
        'loss_date',
        'description',
        'invoice_return_id',
        'status',
        'store_id',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'value' => 'decimal:2',
        'loss_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function reason()
    {
        return $this->belongsTo(LossReason::class, 'loss_reason_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
