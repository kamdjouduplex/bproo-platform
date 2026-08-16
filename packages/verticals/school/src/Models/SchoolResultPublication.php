<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolResultPublication extends TenantModel
{
    use Auditable;

    protected $table = 'school_result_publications';

    protected $fillable = [
        'title',
        'academic_year_id',
        'class_id',
        'publication_rule_id',
        'status',
        'notes',
        'submitted_at',
        'approved_at',
        'published_at',
        'approved_by_name',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function rule()
    {
        return $this->belongsTo(SchoolPublicationRule::class, 'publication_rule_id');
    }

    public function lines()
    {
        return $this->hasMany(SchoolResultPublicationLine::class, 'publication_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }
}
