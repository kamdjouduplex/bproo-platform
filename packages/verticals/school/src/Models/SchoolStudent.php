<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolStudent extends TenantModel
{
    use Auditable;

    protected $table = 'school_students';

    protected $fillable = [
        'student_code',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'parent_full_name',
        'parent_phone',
        'parent_email',
        'photo_path',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function enrollments()
    {
        return $this->hasMany(SchoolEnrollment::class, 'student_id');
    }

    public function payments()
    {
        return $this->hasMany(SchoolPayment::class, 'student_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SchoolStudentLedgerEntry::class, 'student_id');
    }

    public function idCards()
    {
        return $this->hasMany(StudentIdCard::class, 'student_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function photoUrl(?string $tenantCode = null): ?string
    {
        return \School\Support\StudentPhotoStorage::url($this->photo_path, $tenantCode, (int) $this->id);
    }

    /**
     * @return array{percent:int, missing:array<int,string>, filled:int, total:int}
     */
    public function profileCompletion(): array
    {
        return \School\Support\StudentProfileCompletion::for($this);
    }
}

