<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class Sale extends TenantModel
{
    protected $fillable = [
        'sale_number',
        'sale_date',
        'client_id',
        'prescription_id',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'total',
        'currency_code',
        'exchange_rate_to_default',
        'total_in_default',
        'notes',
        'store_id',
        'created_by',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate_to_default' => 'decimal:6',
        'total_in_default' => 'decimal:2',
    ];

    public function currencyLabel(): string
    {
        return \App\Services\TenantCurrencyService::displayLabel($this->currency_code);
    }

    public function lines()
    {
        return $this->hasMany(SaleLine::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function confirmedReturns()
    {
        return $this->returns()->where('status', SaleReturn::STATUS_CONFIRMED);
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class);
    }

    /**
     * Optional relation — only used when the Prescriptions package is installed.
     * Sales must not require that package to load or save.
     */
    public function prescription()
    {
        if (! class_exists(\InovCom\Prescriptions\Models\Prescription::class)) {
            return $this->belongsTo(static::class, 'prescription_id')->whereRaw('0 = 1');
        }

        return $this->belongsTo(\InovCom\Prescriptions\Models\Prescription::class);
    }

    public function creator()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'created_by');
    }

    /** Montants réellement encaissés (hors crédit client). */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('method', '!=', 'credit')->sum('amount');
    }

    public function getCreditAmountAttribute(): float
    {
        return (float) $this->payments()->where('method', 'credit')->sum('amount');
    }

    public function hasCreditPayment(): bool
    {
        return $this->credit_amount > 0.01;
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->total - $this->total_paid);
    }

    public function isFullyPaid(): bool
    {
        return $this->balance <= 0.01 && ! $this->hasCreditPayment();
    }

    public function totalReturned(): float
    {
        if (!\Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('sale_returns')) {
            return 0.0;
        }

        return (float) $this->confirmedReturns()->sum('total_refund');
    }

    public function netTotal(): float
    {
        return max(0, (float) $this->total - $this->totalReturned());
    }

    public function isFullyReturned(): bool
    {
        return $this->total > 0 && $this->totalReturned() >= ((float) $this->total - 0.01);
    }
}
