<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolGradeScale extends TenantModel
{
    protected $table = 'school_grade_scales';

    protected $fillable = [
        'grading_system_id',
        'label',
        'min_percent',
        'max_percent',
        'is_pass',
        'sort_order',
    ];

    protected $casts = [
        'min_percent' => 'float',
        'max_percent' => 'float',
        'is_pass' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function gradingSystem()
    {
        return $this->belongsTo(SchoolGradingSystem::class, 'grading_system_id');
    }
}
