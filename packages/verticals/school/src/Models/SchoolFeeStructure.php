<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolFeeStructure extends TenantModel
{
    use Auditable;

    protected $table = 'school_fee_structures';

    protected $fillable = [
        'name',
        'academic_year_id',
        'class_id',
        'amount',
        'currency_code',
        'is_active',
        'description',
    ];

    protected $casts = [
        'amount' => 'float',
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
