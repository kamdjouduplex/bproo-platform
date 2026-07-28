<?php

namespace Pressing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InovCom\Kernel\TenantModel;

class PressingOrderItem extends TenantModel
{
    protected $table = 'pressing_order_items';

    protected $fillable = [
        'order_id',
        'article_type_id',
        'quantity',
        'weight_kg',
        'price_per_kg',
        'pricing_mode',
        'color',
        'brand',
        'size',
        'notes',
        'condition_on_receipt',
        'photo_path',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'weight_kg' => 'decimal:3',
        'price_per_kg' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PressingOrder::class, 'order_id');
    }

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class, 'article_type_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PressingOrderPiece::class, 'order_item_id')->orderBy('piece_index');
    }
}
