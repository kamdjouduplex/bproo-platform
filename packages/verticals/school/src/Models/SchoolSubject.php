<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolSubject extends TenantModel
{
    protected $table = 'school_subjects';

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

