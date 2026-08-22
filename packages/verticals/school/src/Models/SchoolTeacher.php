<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolTeacher extends TenantModel
{
    protected $table = 'school_teachers';

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function courses()
    {
        return $this->hasMany(SchoolCourse::class, 'teacher_id');
    }
}

