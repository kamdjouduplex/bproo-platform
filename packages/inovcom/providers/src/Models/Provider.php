<?php

namespace InovCom\Providers\Models;

use InovCom\Kernel\TenantModel;

class Provider extends TenantModel
{
    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'is_foreign',
        'default_currency',
        'tax_id',
        'payment_term_id',
        'payment_method',
        'notes',
        'is_active',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'check' => 'Chèque',
        'bank_transfer' => 'Virement',
    ];

    public const CURRENCIES = [
        'EUR' => 'EUR — Euro',
        'USD' => 'USD — Dollar US',
        'GBP' => 'GBP — Livre sterling',
        'XOF' => 'XOF — Franc CFA (BCEAO)',
        'CNY' => 'CNY — Yuan chinois (Renminbi)',
        'NGN' => 'NGN — Naira nigérian',
        'CAD' => 'CAD — Dollar canadien',
        'INR' => 'INR — Roupie indienne',
    ];

    /** @return list<string> */
    public static function currencyCodes(): array
    {
        return array_keys(self::CURRENCIES);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_foreign' => 'boolean',
    ];

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function contacts()
    {
        return $this->hasMany(ProviderContact::class);
    }

    public function primaryContact()
    {
        return $this->hasOne(ProviderContact::class)->where('is_primary', true);
    }

    public static function paymentMethodLabel(?string $method): string
    {
        if ($method === null || $method === '') {
            return '—';
        }

        return self::PAYMENT_METHODS[$method] ?? $method;
    }
}
