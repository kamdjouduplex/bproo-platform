<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolClass extends TenantModel
{
    protected $table = 'school_classes';

    protected $fillable = [
        'name',
        'section',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function enrollments()
    {
        return $this->hasMany(SchoolEnrollment::class, 'class_id');
    }
}

