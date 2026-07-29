<?php

namespace InovCom\Stock\Models;

use InovCom\Kernel\TenantModel;

class StorageLocation extends TenantModel
{
    protected $fillable = [
        'store_id',
        'zone',
        'aisle',
        'shelf',
        'bin',
        'code',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->belongsToMany(
            \InovCom\Items\Models\Item::class,
            'item_storage_locations',
            'storage_location_id',
            'item_id'
        )->withPivot('is_primary')->withTimestamps();
    }

    public function formatLabel(): string
    {
        return $this->code;
    }

    public static function buildCode(string $zone, ?string $aisle, ?string $shelf, ?string $bin): string
    {
        $parts = [trim($zone)];
        if ($aisle !== null && trim($aisle) !== '') {
            $parts[] = trim($aisle);
        }
        if ($shelf !== null && trim($shelf) !== '') {
            $parts[] = trim($shelf);
        }
        if ($bin !== null && trim($bin) !== '') {
            $parts[] = trim($bin);
        }

        return implode('-', $parts);
    }

    public static function buildLabel(string $zone, ?string $aisle, ?string $shelf, ?string $bin, ?string $code = null): string
    {
        $segments = ['Rayon ' . trim($zone)];
        if ($aisle !== null && trim($aisle) !== '') {
            $segments[] = 'Allée ' . trim($aisle);
        }
        if ($shelf !== null && trim($shelf) !== '') {
            $segments[] = 'Étagère ' . trim($shelf);
        }
        if ($bin !== null && trim($bin) !== '') {
            $segments[] = 'Casier ' . trim($bin);
        }

        $label = implode(' · ', $segments);

        if ($code) {
            return $label . ' (' . $code . ')';
        }

        return $label;
    }
}
