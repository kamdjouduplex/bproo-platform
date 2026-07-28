<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InovCom\Kernel\TenantModel;

class ArticlePrice extends TenantModel
{
    protected $table = 'article_prices';

    protected $fillable = [
        'article_type_id',
        'agence_id',
        'amount',
        'price_per_kg',
        'pricing_mode',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class, 'article_type_id');
    }

    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }
}
