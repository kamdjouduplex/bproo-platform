<?php

namespace InovCom\Expenses\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Expense extends TenantModel
{
    protected $fillable = [
        'reference',
        'expense_category_id',
        'amount',
        'expense_date',
        'description',
        'payment_method',
        'status',
        'store_id',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approval()
    {
        return $this->morphOne(Approval::class, 'approvable');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeRejected(): bool
    {
        return $this->status === 'pending';
    }

    public function canBePaid(): bool
    {
        return $this->status === 'approved';
    }
}
