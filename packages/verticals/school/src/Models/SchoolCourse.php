<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use School\Support\SchoolTimetable;

class SchoolCourse extends TenantModel
{
    use Auditable;

    protected $table = 'school_courses';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'room_id',
        'room',
        'color',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $course) {
            if (! filled($course->color)) {
                $course->color = SchoolTimetable::colorFor((int) $course->subject_id);
            }
        });
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(SchoolSubject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(SchoolTeacher::class, 'teacher_id');
    }

    public function schoolRoom()
    {
        return $this->belongsTo(SchoolRoom::class, 'room_id');
    }

    public function slots()
    {
        return $this->hasMany(SchoolTimetableSlot::class, 'course_id');
    }

    public function displayColor(): string
    {
        return $this->color ?: SchoolTimetable::colorFor((int) $this->subject_id);
    }

    public function label(): string
    {
        $subject = $this->subject?->name ?? 'Cours';
        $teacher = $this->teacher?->full_name;

        return $teacher ? $subject.' — '.$teacher : $subject;
    }

    public function roomLabel(): string
    {
        return (string) ($this->schoolRoom?->name ?: $this->room ?: '');
    }
}
