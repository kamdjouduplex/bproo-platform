<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;

class PayrollLine extends TenantModel
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'base_salary',
        'bonuses',
        'deductions',
        'net_salary',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(PayrollLineItem::class)->orderBy('sort_order');
    }
}
