<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use InovCom\Kernel\TenantModel;
use Pressing\Support\PressingBilling;

class ArticleType extends TenantModel
{
    protected $table = 'article_types';

    protected $fillable = [
        'name',
        'code',
        'sort_order',
        'is_active',
        'pricing_mode',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function pricingModeForAgence(?int $agenceId = null): string
    {
        return PressingBilling::resolveArticleMode($this, $agenceId);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ArticlePrice::class, 'article_type_id');
    }

    public function priceForAgence(?int $agenceId = null): float
    {
        return (float) ($this->priceRowForAgence($agenceId)?->amount ?? 0);
    }

    public function pricePerKgForAgence(?int $agenceId = null): float
    {
        return (float) ($this->priceRowForAgence($agenceId)?->price_per_kg ?? 0);
    }

    public function priceRowForAgence(?int $agenceId = null): ?ArticlePrice
    {
        $query = $this->prices()->where('is_active', true);

        if ($agenceId) {
            $specific = (clone $query)->where('agence_id', $agenceId)->first();
            if ($specific) {
                return $specific;
            }
        }

        return $query->whereNull('agence_id')->first();
    }
}
