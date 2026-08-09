<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCurrency extends Model
{
    protected $table = 'platform_currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimals',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'decimals' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public function label(): string
    {
        $sym = $this->symbol ? " ({$this->symbol})" : '';

        return "{$this->code} — {$this->name}{$sym}";
    }
}
