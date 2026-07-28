<?php

namespace InovCom\Stock\Models;

use InovCom\Items\Models\Item;
use InovCom\Kernel\TenantModel;

class ItemStorageLocation extends TenantModel
{
    protected $fillable = [
        'item_id',
        'storage_location_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function storageLocation()
    {
        return $this->belongsTo(StorageLocation::class);
    }
}
