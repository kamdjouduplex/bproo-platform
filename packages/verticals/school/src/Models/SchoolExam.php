<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolExam extends TenantModel
{
    use Auditable;

    protected $table = 'school_exams';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'title',
        'exam_date',
        'max_score',
        'coefficient',
        'status',
        'notes',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'max_score' => 'float',
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

    public function teacher()
    {
        return $this->belongsTo(SchoolTeacher::class, 'teacher_id');
    }

    public function marks()
    {
        return $this->hasMany(SchoolExamMark::class, 'exam_id');
    }

    public function isEditable(): bool
    {
        return $this->status !== 'closed';
    }
}
