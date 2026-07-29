<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class EmployeeSalaryHistory extends TenantModel
{
    protected $table = 'employee_salary_history';

    protected $fillable = [
        'employee_id',
        'amount',
        'previous_amount',
        'effective_date',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'previous_amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
