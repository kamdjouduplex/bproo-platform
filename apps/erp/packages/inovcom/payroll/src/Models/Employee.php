<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class Employee extends TenantModel
{
    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'department',
        'contract_type',
        'cnps_number',
        'bank_name',
        'bank_account',
        'gender',
        'birth_date',
        'address',
        'annual_leave_days',
        'notes',
        'base_salary',
        'hire_date',
        'termination_date',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'annual_leave_days' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payrollLines()
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function salaryHistory()
    {
        return $this->hasMany(EmployeeSalaryHistory::class)->orderByDesc('effective_date');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class)->orderByDesc('start_date');
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function payrollAdjustments()
    {
        return $this->hasMany(EmployeePayrollAdjustment::class)->orderByDesc('period_start');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function hasSystemAccount(): bool
    {
        return !empty($this->user_id);
    }
}
