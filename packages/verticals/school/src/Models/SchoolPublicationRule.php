<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolPublicationRule extends TenantModel
{
    protected $table = 'school_publication_rules';

    protected $fillable = [
        'name',
        'academic_year_id',
        'class_id',
        'require_fees_paid',
        'min_fees_amount',
        'require_validated_marks',
        'require_director_approval',
        'is_active',
        'description',
    ];

    protected $casts = [
        'require_fees_paid' => 'boolean',
        'min_fees_amount' => 'float',
        'require_validated_marks' => 'boolean',
        'require_director_approval' => 'boolean',
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
}
