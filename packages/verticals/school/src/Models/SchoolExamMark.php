<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolExamMark extends TenantModel
{
    use Auditable;

    protected $table = 'school_exam_marks';

    protected $fillable = [
        'exam_id',
        'student_id',
        'score',
        'is_absent',
        'remarks',
        'validated_at',
    ];

    protected $casts = [
        'score' => 'float',
        'is_absent' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(SchoolExam::class, 'exam_id');
    }

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }
}
