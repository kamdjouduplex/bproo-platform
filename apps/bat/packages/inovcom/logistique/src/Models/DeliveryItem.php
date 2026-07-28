<?php

namespace InovCom\Logistique\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Stock\Models\Product;

class DeliveryItem extends TenantModel
{
    protected $table = 'delivery_items';

    protected $fillable = ['delivery_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function delivery(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
