<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PressingOrderPiece extends TenantModel
{
    public const SIZE_OPTIONS = ['', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'Enfant', 'Autre'];

    protected $table = 'pressing_order_pieces';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'piece_index',
        'label',
        'color',
        'size',
        'fabric',
        'defects',
        'notes',
        'sorted_at',
        'sorted_by',
    ];

    protected $casts = [
        'piece_index' => 'integer',
        'sorted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PressingOrderItem::class, 'order_item_id');
    }

    public function sorter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by');
    }
}
