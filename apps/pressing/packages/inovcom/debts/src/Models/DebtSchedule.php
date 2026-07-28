<?php

namespace InovCom\Debts\Models;

use InovCom\Kernel\TenantModel;

class DebtSchedule extends TenantModel
{
    protected $table = 'debt_schedules';

    protected $fillable = [
        'debt_id',
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
    ];

    public function debt()
    {
        return $this->belongsTo(Debt::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function getRemainingAttribute(): float
    {
        return (float) $this->amount_due - (float) $this->amount_paid;
    }
}
