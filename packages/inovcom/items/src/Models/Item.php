<?php

namespace InovCom\Items\Models;

use InovCom\Kernel\TenantModel;

class Item extends TenantModel
{
    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'description',
        'category_id',
        'brand_id',
        'unit_id',
        'price',
        'cost',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function unitPrices()
    {
        return $this->hasMany(ItemUnitPrice::class)->orderBy('is_default', 'desc')->orderBy('unit_id');
    }

    public function defaultUnitPrice()
    {
        return $this->hasOne(ItemUnitPrice::class)->where('is_default', true);
    }

    public function setComponents()
    {
        return $this->hasMany(ItemSetComponent::class, 'set_item_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isProductSet(): bool
    {
        return !empty(($this->metadata ?? [])['is_set']);
    }

    /** Référence article (alias du champ sku en base). */
    public function getReferenceAttribute(): ?string
    {
        return $this->sku;
    }

    public function getDisplayLabelAttribute(): string
    {
        return item_display($this->sku, $this->name);
    }

    /**
     * Get selling units with prices for POS.
     */
    public function getSellingUnitsAttribute(): array
    {
        $prices = $this->unitPrices()->with('unit')->get();
        if ($prices->isEmpty()) {
            $unit = $this->unit;
            return [[
                'unit_id' => $unit?->id,
                'unit_name' => $unit?->name ?? 'pc',
                'unit_abbr' => $unit?->abbreviation ?? $unit?->name ?? 'pc',
                'conversion_factor' => 1,
                'price' => (float) $this->price,
                'cost' => (float) $this->cost,
            ]];
        }
        return $prices->map(fn ($p) => [
            'unit_id' => $p->unit_id,
            'unit_name' => $p->unit->name,
            'unit_abbr' => $p->unit->abbreviation ?? $p->unit->name,
            'conversion_factor' => (float) $p->conversion_factor,
            'price' => (float) $p->price,
            'cost' => (float) $p->cost,
        ])->toArray();
    }
}
