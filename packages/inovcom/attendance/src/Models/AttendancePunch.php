<?php

namespace InovCom\Attendance\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;

class AttendancePunch extends TenantModel
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';

    public const TYPE_LABELS = [
        self::TYPE_IN => 'Arrivée',
        self::TYPE_OUT => 'Départ',
    ];

    protected $fillable = [
        'user_id',
        'employee_id',
        'attendance_date',
        'punched_at',
        'punch_type',
        'source',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'punched_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(\InovCom\Payroll\Models\Employee::class);
    }
}
