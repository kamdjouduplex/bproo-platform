<?php

namespace InovCom\Reservations\Models;

use InovCom\Clients\Models\Client;
use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Reservation extends TenantModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_CONVERTED => 'Convertie en devis',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    protected $fillable = [
        'reference',
        'client_id',
        'status',
        'reservation_date',
        'expected_date',
        'notes',
        'quotation_id',
        'store_id',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'converted_at',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'expected_date' => 'date',
        'cancelled_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function lines()
    {
        return $this->hasMany(ReservationLine::class)->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function quotation()
    {
        if (!class_exists(\InovCom\Quotations\Models\Quotation::class)) {
            return $this->belongsTo(Client::class)->whereRaw('0=1');
        }

        return $this->belongsTo(\InovCom\Quotations\Models\Quotation::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function activeQuantityTotal(): float
    {
        return (float) $this->lines->sum(fn (ReservationLine $line) => $line->active_quantity);
    }
}
