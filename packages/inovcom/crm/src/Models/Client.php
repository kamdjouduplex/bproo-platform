<?php

namespace InovCom\Clients\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use InovCom\Kernel\TenantModel;

class Client extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'email',
        'phone',
        'address',
        'tax_id',
        'rccm',
        'niu',
        'bp',
        'segment_id',
        'zone_id',
        'category_id',
        'payment_term_id',
        'payment_method',
        'salesrep_id',
        'credit_limit',
        'discount_rate',
        'price_tier',
        'current_balance',
        'is_active',
        'is_blocked',
        'block_reason',
        'blocked_at',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'blocked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'check' => 'Chèque',
        'bank_transfer' => 'Virement bancaire',
        'credit' => 'Crédit',
    ];

    public const PRICE_TIERS = [
        'retail' => 'Détail',
        'semi_wholesale' => 'Demi-gros',
        'wholesale' => 'Gros',
    ];

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function category()
    {
        return $this->belongsTo(ClientCategory::class, 'category_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function creditLimits()
    {
        return $this->hasMany(CreditLimit::class);
    }

    public function clientNotes()
    {
        return $this->hasMany(ClientNote::class)->orderByDesc('created_at');
    }

    public function documents()
    {
        return $this->hasMany(ClientDocument::class)->orderByDesc('created_at');
    }

    public function reminders()
    {
        return $this->hasMany(ClientReminder::class)->orderByDesc('created_at');
    }

    public function defaultAddress()
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    public function billingAddress()
    {
        return $this->hasOne(Address::class)->whereIn('type', ['billing', 'both'])->orderByDesc('is_default');
    }

    public function shippingAddress()
    {
        return $this->hasOne(Address::class)->whereIn('type', ['shipping', 'both'])->orderByDesc('is_default');
    }

    public function primaryContact()
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? '—';
    }

    public function priceTierLabel(): string
    {
        return self::PRICE_TIERS[$this->price_tier] ?? self::PRICE_TIERS['retail'];
    }

    public function isBlocked(): bool
    {
        return (bool) $this->is_blocked;
    }
}
