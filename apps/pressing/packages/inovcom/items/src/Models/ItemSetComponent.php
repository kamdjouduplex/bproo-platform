<?php

namespace InovCom\Items\Models;

use InovCom\Kernel\TenantModel;

class ItemSetComponent extends TenantModel
{
    protected $table = 'item_set_components';

    protected $fillable = [
        'set_item_id',
        'component_item_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'sort_order' => 'integer',
    ];

    public function setItem()
    {
        return $this->belongsTo(Item::class, 'set_item_id');
    }

    public function componentItem()
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
