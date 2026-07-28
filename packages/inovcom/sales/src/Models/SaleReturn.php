<?php

namespace InovCom\Sales\Models;

use InovCom\Kernel\TenantModel;

class SaleReturn extends TenantModel
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_PARTIAL = 'partial';
    public const TYPE_FULL = 'full';

    protected $fillable = [
        'return_number',
        'sale_id',
        'status',
        'type',
        'return_date',
        'subtotal_refund',
        'discount_refund',
        'total_refund',
        'reason',
        'notes',
        'store_id',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal_refund' => 'decimal:2',
        'discount_refund' => 'decimal:2',
        'total_refund' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function lines()
    {
        return $this->hasMany(SaleReturnLine::class);
    }

    public function refunds()
    {
        return $this->hasMany(SaleReturnRefund::class);
    }

    public function creator()
    {
        return $this->belongsTo(\InovCom\Users\Models\User::class, 'created_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_CONFIRMED => 'Confirmé',
            self::STATUS_CANCELLED => 'Annulé',
            default => $status,
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_FULL => 'Retour total',
            self::TYPE_PARTIAL => 'Retour partiel',
            default => $type,
        };
    }

    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'defect' => 'Produit défectueux',
            'wrong_item' => 'Erreur de livraison',
            'client_request' => 'Demande client',
            'expired' => 'Périmé / non conforme',
            'bad_reference' => 'Mauvaise référence',
            'poor_quality' => 'Qualité insatisfaisante',
            'other' => 'Autre',
            default => $reason ?? '—',
        };
    }
}
