<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class Payment extends TenantModel
{
    public const METHOD_LABELS = [
        'cash' => 'Espèces',
        'orange_money' => 'Orange Money',
        'mtn_money' => 'MTN Money',
        'credit' => 'Crédit',
        'mobile_money' => 'Mobile Money',
        'card' => 'Carte',
        'check' => 'Chèque',
        'bank_transfer' => 'Virement',
        'other' => 'Autre',
    ];

    protected $fillable = [
        'sale_id',
        'method',
        'mobile_money_provider',
        'transaction_reference',
        'amount',
        'currency_code',
        'exchange_rate_to_default',
        'amount_in_default',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate_to_default' => 'decimal:6',
        'amount_in_default' => 'decimal:2',
    ];

    public function currencyLabel(): string
    {
        return \App\Services\TenantCurrencyService::label($this->currency_code);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function receiver()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'received_by');
    }

    /** Human-readable method label (supports legacy mobile_money + provider). */
    public function getMethodLabelAttribute(): string
    {
        if ($this->method === 'mobile_money' && $this->mobile_money_provider) {
            return match (strtolower($this->mobile_money_provider)) {
                'orange' => 'Orange Money',
                'mtn' => 'MTN Money',
                'moov' => 'Moov Money',
                default => self::METHOD_LABELS['mobile_money'] ?? 'Mobile Money',
            };
        }
        return self::METHOD_LABELS[$this->method] ?? ucfirst($this->method ?? '');
    }
}
