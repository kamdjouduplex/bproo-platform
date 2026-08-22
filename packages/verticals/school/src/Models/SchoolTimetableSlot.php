<?php

namespace School\Models;

use InovCom\Kernel\TenantModel;
use InovCom\Kernel\Traits\Auditable;
use School\Support\SchoolTimetable;

class SchoolTimetableSlot extends TenantModel
{
    use Auditable;
    protected $table = 'school_timetable_slots';

    protected $fillable = [
        'course_id',
        'weekday',
        'start_time',
        'end_time',
        'room_id',
        'room',
    ];

    public function course()
    {
        return $this->belongsTo(SchoolCourse::class, 'course_id');
    }

    public function schoolRoom()
    {
        return $this->belongsTo(SchoolRoom::class, 'room_id');
    }

    public function startHm(): string
    {
        $raw = $this->start_time;
        if (is_object($raw) && method_exists($raw, 'format')) {
            return $raw->format('H:i');
        }

        return SchoolTimetable::formatTime((string) $raw);
    }

    public function endHm(): string
    {
        $raw = $this->end_time;
        if (is_object($raw) && method_exists($raw, 'format')) {
            return $raw->format('H:i');
        }

        return SchoolTimetable::formatTime((string) $raw);
    }

    public function roomLabel(): string
    {
        return (string) (
            $this->schoolRoom?->name
            ?: $this->room
            ?: $this->course?->schoolRoom?->name
            ?: $this->course?->room
            ?: ''
        );
    }

    public function sessionLabel(): string
    {
        $subject = $this->course?->subject?->name ?? 'Cours';
        $teacher = $this->course?->teacher?->full_name;
        $room = $this->roomLabel();

        return trim(
            $this->startHm().'–'.$this->endHm()
            .' · '.$subject
            .($teacher ? ' · '.$teacher : '')
            .($room ? ' · '.$room : '')
        );
    }
}
