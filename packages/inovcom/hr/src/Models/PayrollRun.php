<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class PayrollRun extends TenantModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_PAID = 'paid';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_PROCESSED => 'Traitée',
        self::STATUS_PAID => 'Payée',
    ];

    protected $fillable = [
        'reference',
        'period_start',
        'period_end',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'processed_at',
        'paid_at',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'processed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Fiche définitive : plus aucune modification ni annulation.
     */
    public function isLocked(): bool
    {
        return $this->isPaid();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
