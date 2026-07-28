<?php

namespace InovCom\Quotations\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Quotation extends TenantModel
{
    protected $fillable = [
        'number',
        'client_id',
        'parent_quotation_id',
        'revision',
        'quote_date',
        'valid_until',
        'status',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'discount_mode',
        'apply_tax',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'customer_purchase_order',
        'show_markup_coefficient',
        'store_id',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'apply_tax' => 'boolean',
        'show_markup_coefficient' => 'boolean',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lines()
    {
        return $this->hasMany(QuotationLine::class)->orderBy('id');
    }

    public function taxLines()
    {
        return $this->hasMany(QuotationTaxLine::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    public function revisions()
    {
        return $this->hasMany(self::class, 'parent_quotation_id')->orderBy('revision');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent', 'suspended'], true);
    }

    public function isAccepted(): bool
    {
        return in_array($this->status, ['accepted', 'validated'], true);
    }

    /** @deprecated Use isAccepted() */
    public function isValidated(): bool
    {
        return $this->isAccepted();
    }

    public function canCreateInvoice(): bool
    {
        return $this->isAccepted();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted', 'validated' => 'Accepté',
            'suspended' => 'Suspendu',
            'rejected' => 'Rejeté',
            default => $status,
        };
    }
}
