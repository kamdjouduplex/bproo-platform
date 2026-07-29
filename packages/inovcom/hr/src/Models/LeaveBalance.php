<?php

namespace InovCom\Payroll\Models;

use InovCom\Kernel\TenantModel;

class LeaveBalance extends TenantModel
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'allocated',
        'used',
        'remaining',
    ];

    protected $casts = [
        'allocated' => 'decimal:2',
        'used' => 'decimal:2',
        'remaining' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
