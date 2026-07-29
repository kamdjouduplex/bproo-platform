<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class EmployeePayrollAdjustment extends TenantModel
{
    public const TYPE_UNPAID_DAYS = 'unpaid_days';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_LABELS = [
        self::TYPE_UNPAID_DAYS => 'Jours non payés',
        self::TYPE_BONUS => 'Prime / gain',
        self::TYPE_DEDUCTION => 'Retenue',
    ];

    protected $fillable = [
        'employee_id',
        'payroll_run_id',
        'period_start',
        'period_end',
        'type',
        'days',
        'amount',
        'label',
        'recorded_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'days' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function isLocked(): bool
    {
        if (!$this->payroll_run_id) {
            return false;
        }

        $run = $this->payrollRun;
        return $run && !$run->isDraft();
    }
}
