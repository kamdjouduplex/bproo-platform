<?php

namespace InovCom\Prescriptions\Models;

use InovCom\Kernel\TenantModel;

class Prescription extends TenantModel
{
    protected $fillable = [
        'number',
        'client_id',
        'prescriber_name',
        'prescriber_contact',
        'valid_from',
        'valid_until',
        'status',
        'notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISPENSED = 'dispensed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public function client()
    {
        return $this->belongsTo(\InovCom\Clients\Models\Client::class);
    }

    public function lines()
    {
        return $this->hasMany(PrescriptionLine::class)->orderBy('sort_order');
    }

    public function isDispensed(): bool
    {
        return $this->status === self::STATUS_DISPENSED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPartiallyDispensed(): bool
    {
        $this->loadMissing('lines');
        if ($this->lines->isEmpty()) {
            return false;
        }

        $anyDispensed = $this->lines->contains(fn ($l) => (float) $l->quantity_dispensed > 0.0001);
        $anyRemaining = $this->lines->contains(fn ($l) => $l->remaining_quantity > 0.0001);

        return $anyDispensed && $anyRemaining && $this->status !== self::STATUS_DISPENSED;
    }

    public function dispensationStatusLabel(): string
    {
        return match (true) {
            $this->status === self::STATUS_DISPENSED => 'Dispensée',
            $this->status === self::STATUS_CANCELLED => 'Annulée',
            $this->status === self::STATUS_EXPIRED => 'Expirée',
            $this->status === self::STATUS_DRAFT => 'Brouillon',
            $this->isPartiallyDispensed() => 'Partiellement délivrée',
            default => 'Active',
        };
    }
}
