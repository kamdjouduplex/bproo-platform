<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;

class SchoolStudentLedgerEntry extends TenantModel
{
    protected $table = 'school_student_ledger_entries';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'entry_type',
        'amount',
        'balance_after',
        'label',
        'source_type',
        'source_id',
        'notes',
        'created_by_name',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_after' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
