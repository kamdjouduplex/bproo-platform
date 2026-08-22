<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Users\Models\User;
use School\Support\TeacherPhotoStorage;

class SchoolTeacher extends TenantModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    protected $table = 'school_teachers';

    protected $fillable = [
        'teacher_code',
        'first_name',
        'last_name',
        'full_name',
        'gender',
        'phone',
        'email',
        'address',
        'photo_path',
        'education_level',
        'diploma_kind',
        'diploma_label',
        'studies_in_progress',
        'teaching_section',
        'schedule_note',
        'remuneration_amount',
        'profile_status',
        'validated_at',
        'validated_by',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'remuneration_amount' => 'decimal:2',
        'validated_at' => 'datetime',
        'user_id' => 'integer',
        'validated_by' => 'integer',
    ];

    public function courses()
    {
        return $this->hasMany(SchoolCourse::class, 'teacher_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(SchoolSubject::class, 'school_teacher_subject', 'teacher_id', 'subject_id')
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isValidated(): bool
    {
        return ($this->profile_status ?? self::STATUS_DRAFT) === self::STATUS_VALIDATED;
    }

    public function isOwnedBy(?object $user): bool
    {
        if (! $user || ! $this->user_id) {
            return false;
        }

        return (int) $this->user_id === (int) $user->id;
    }

    public function photoUrl(?string $tenantCode = null): ?string
    {
        return TeacherPhotoStorage::url($this->photo_path, $tenantCode, (int) $this->id);
    }

    public static function forUser(?object $user): ?self
    {
        if (! $user) {
            return null;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('school_teachers', 'user_id')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return static::query()->where('user_id', $user->id)->first();
    }
}
