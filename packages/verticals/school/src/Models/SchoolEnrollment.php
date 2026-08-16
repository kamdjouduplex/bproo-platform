<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolEnrollment extends TenantModel
{
    use Auditable;

    protected $table = 'school_enrollments';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_id',
        'section',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}

