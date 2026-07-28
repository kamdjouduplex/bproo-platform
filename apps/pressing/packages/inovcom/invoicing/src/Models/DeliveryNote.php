<?php

namespace InovCom\Invoicing\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class DeliveryNote extends TenantModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'delivery_number',
        'invoice_id',
        'quotation_id',
        'client_id',
        'status',
        'delivery_date',
        'notes',
        'customer_purchase_order',
        'show_prices',
        'show_discounts',
        'store_id',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'show_prices' => 'boolean',
        'show_discounts' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function quotation()
    {
        return $this->belongsTo(\InovCom\Quotations\Models\Quotation::class);
    }

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class);
    }

    /**
     * Le BL est issu d'un devis (workflow Devis → Livraison → Facture).
     */
    public function isFromQuotation(): bool
    {
        return $this->quotation_id !== null;
    }

    public function lines()
    {
        return $this->hasMany(DeliveryNoteLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_CONFIRMED => 'Livré',
            self::STATUS_CANCELLED => 'Annulé',
            default => $status,
        };
    }
}
