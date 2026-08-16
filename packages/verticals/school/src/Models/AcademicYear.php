<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class AcademicYear extends TenantModel
{
    use Auditable;

    protected $table = 'academic_years';

    protected $fillable = [
        'code',
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function enrollments()
    {
        return $this->hasMany(SchoolEnrollment::class, 'academic_year_id');
    }

    public function payments()
    {
        return $this->hasMany(SchoolPayment::class, 'academic_year_id');
    }

    public function idCards()
    {
        return $this->hasMany(StudentIdCard::class, 'academic_year_id');
    }
}

