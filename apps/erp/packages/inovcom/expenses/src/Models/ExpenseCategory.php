<?php

namespace InovCom\Expenses\Models;

use InovCom\Kernel\TenantModel;

class ExpenseCategory extends TenantModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function getTotalExpensesAttribute(): float
    {
        return $this->expenses()->where('status', '!=', 'rejected')->sum('amount');
    }
}
