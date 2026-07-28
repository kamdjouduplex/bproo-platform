<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;

class LeaveType extends TenantModel
{
    protected $fillable = [
        'code',
        'name',
        'is_paid',
        'requires_approval',
        'is_active',
        'default_days_per_year',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
