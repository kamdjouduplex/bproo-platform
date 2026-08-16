<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolResultPublicationLine extends TenantModel
{
    protected $table = 'school_result_publication_lines';

    protected $fillable = [
        'publication_id',
        'student_id',
        'average',
        'grade_label',
        'passed',
        'promoted',
        'eligible',
        'is_published',
        'checks',
        'blocked_reasons',
    ];

    protected $casts = [
        'average' => 'float',
        'passed' => 'boolean',
        'promoted' => 'boolean',
        'eligible' => 'boolean',
        'is_published' => 'boolean',
        'checks' => 'array',
    ];

    public function publication()
    {
        return $this->belongsTo(SchoolResultPublication::class, 'publication_id');
    }

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }
}
