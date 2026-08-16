<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolSubjectCoefficient extends TenantModel
{
    protected $table = 'school_subject_coefficients';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'subject_id',
        'coefficient',
    ];

    protected $casts = [
        'coefficient' => 'float',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'subject_id');
    }
}
