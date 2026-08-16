<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolGradingRule extends TenantModel
{
    protected $table = 'school_grading_rules';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'grading_system_id',
        'pass_mark',
        'promotion_average',
        'max_failed_subjects',
        'ranking_method',
        'absent_counts_as_zero',
        'require_validated_marks',
        'is_active',
    ];

    protected $casts = [
        'pass_mark' => 'float',
        'promotion_average' => 'float',
        'max_failed_subjects' => 'integer',
        'absent_counts_as_zero' => 'boolean',
        'require_validated_marks' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function gradingSystem()
    {
        return $this->belongsTo(SchoolGradingSystem::class, 'grading_system_id');
    }
}
