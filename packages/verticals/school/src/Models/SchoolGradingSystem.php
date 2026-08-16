<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolGradingSystem extends TenantModel
{
    protected $table = 'school_grading_systems';

    protected $fillable = [
        'code',
        'name',
        'scale_base',
        'description',
        'is_active',
    ];

    protected $casts = [
        'scale_base' => 'float',
        'is_active' => 'boolean',
    ];

    public function scales()
    {
        return $this->hasMany(SchoolGradeScale::class, 'grading_system_id')->orderBy('sort_order');
    }

    public function rules()
    {
        return $this->hasMany(SchoolGradingRule::class, 'grading_system_id');
    }
}
