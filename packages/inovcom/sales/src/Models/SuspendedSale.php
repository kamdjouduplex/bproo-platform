<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class SuspendedSale extends TenantModel
{
    protected $table = 'suspended_sales';

    protected $fillable = [
        'user_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class);
    }

    /** Short label for display (e.g. "3 articles, 12 500 FCFA"). */
    public function getSummaryAttribute(): string
    {
        $payload = $this->payload ?? [];
        $cart = $payload['cart'] ?? [];
        $count = count($cart);
        $total = $payload['total'] ?? 0;
        $totalFormatted = $total > 0 ? fmt_money((float) $total) . ' FCFA' : '—';
        $articleWord = $count <= 1 ? 'article' : 'articles';
        return $count . ' ' . $articleWord . ', ' . $totalFormatted;
    }
}
