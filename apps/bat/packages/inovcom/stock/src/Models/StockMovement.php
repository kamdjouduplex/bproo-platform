<?php

namespace InovCom\Stock\Models;

use InovCom\Kernel\TenantModel;

class StockMovement extends TenantModel
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity', 'type',
        'reference_type', 'reference_id', 'user_id', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function typeLabel(): string
    {
        return match($this->type) {
            'IN'       => 'Entrée',
            'OUT'      => 'Sortie',
            'TRANSFER' => 'Transfert',
            default    => $this->type,
        };
    }

    public function typeBadgeClass(): string
    {
        return match($this->type) {
            'IN'       => 'badge badge-success',
            'OUT'      => 'badge badge-danger',
            'TRANSFER' => 'badge badge-info',
            default    => 'badge badge-secondary',
        };
    }

    public static function referenceTypes(): array
    {
        return [
            'adjustment' => 'Ajustement manuel',
            'purchase'   => 'Bon d\'achat',
            'project'    => 'Projet',
            'delivery'   => 'Livraison',
            'other'      => 'Autre',
        ];
    }
}
