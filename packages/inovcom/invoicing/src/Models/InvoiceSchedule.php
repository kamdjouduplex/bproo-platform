<?php

namespace InovCom\Invoicing\Models;

use InovCom\Kernel\TenantModel;

class InvoiceSchedule extends TenantModel
{
    protected $table = 'invoice_schedules';

    protected $fillable = [
        'invoice_id',
        'installment_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'status',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'installment_number' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->amount_due - (float) $this->amount_paid, 2));
    }

    public function getRemainingAttribute(): float
    {
        return $this->remaining();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->remaining() <= 0.01;
    }

    public function isDue(): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $this->status === 'overdue'
            || $this->due_date->lte(\Carbon\Carbon::today());
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'À venir',
            'partial' => 'Partielle',
            'paid' => 'Payée',
            'overdue' => 'Due / en retard',
            default => $status,
        };
    }
}
