<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;

class SchoolAttendanceRecord extends TenantModel
{
    use Auditable;

    protected $table = 'school_attendance_records';

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_EXCUSED = 'excused';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'course_id',
        'timetable_slot_id',
        'student_id',
        'attendance_date',
        'status',
        'remark',
        'recorded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_PRESENT => 'Présent',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_LATE => 'Retard',
            self::STATUS_EXCUSED => 'Excusé',
        ];
    }

    public function student()
    {
        return $this->belongsTo(SchoolStudent::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function course()
    {
        return $this->belongsTo(SchoolCourse::class, 'course_id');
    }

    public function timetableSlot()
    {
        return $this->belongsTo(SchoolTimetableSlot::class, 'timetable_slot_id');
    }
}
