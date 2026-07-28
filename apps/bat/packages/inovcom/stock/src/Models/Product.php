<?php

namespace InovCom\Stock\Models;

use InovCom\Kernel\TenantModel;

class Product extends TenantModel
{
    protected $table = 'products';

    protected $fillable = [
        'code', 'name', 'category', 'unit', 'description', 'min_stock_alert', 'is_active',
    ];

    protected $casts = [
        'min_stock_alert' => 'decimal:3',
        'is_active'       => 'boolean',
    ];

    public function movements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public static function generateCode(): string
    {
        $max = static::on('tenant')
            ->where('code', 'like', 'PRD%')
            ->pluck('code')
            ->map(fn(string $c): int => (int) substr($c, 3))
            ->filter(fn(int $n): bool => $n > 0)
            ->max();

        return 'PRD' . str_pad((string)(($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public static function units(): array
    {
        return ['pièce', 'kg', 'g', 't', 'litre', 'm³', 'm', 'm²', 'sac', 'carton', 'bobine', 'palette'];
    }

    public static function categories(): array
    {
        return ['Matériaux', 'Outillage', 'Équipements', 'Consommables', 'Fournitures', 'Autre'];
    }
}
