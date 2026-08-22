<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\AcademicYear;
use School\Models\SchoolAttendanceRecord;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolTimetableSlot;

class SchoolAttendancePrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request): View
    {
        $this->authorizeSchoolPermission('school_attendance.view');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $yearId = $request->integer('year');
        $classId = $request->integer('class');
        $date = $request->query('date') ?: now()->toDateString();
        $slotId = $request->integer('slot');

        $year = $yearId ? AcademicYear::query()->find($yearId) : AcademicYear::query()->where('is_active', true)->first();
        $class = $classId ? SchoolClass::query()->find($classId) : null;
        $slot = $slotId
            ? SchoolTimetableSlot::query()->with(['course.subject', 'course.teacher'])->find($slotId)
            : null;

        $roster = collect();
        if ($year && $class) {
            $existingQuery = SchoolAttendanceRecord::query()
                ->where('class_id', $class->id)
                ->whereDate('attendance_date', $date);
            if ($slot) {
                $existingQuery->where('timetable_slot_id', $slot->id);
            } else {
                $existingQuery->whereNull('course_id');
            }
            $existing = $existingQuery->get()->keyBy('student_id');

            $roster = SchoolEnrollment::query()
                ->with('student')
                ->where('academic_year_id', $year->id)
                ->where('class_id', $class->id)
                ->where('status', 'enrolled')
                ->get()
                ->sortBy(fn ($e) => mb_strtolower($e->student?->last_name.' '.$e->student?->first_name))
                ->map(function ($e) use ($existing) {
                    $row = $existing->get($e->student_id);

                    return [
                        'student' => $e->student,
                        'status' => $row?->status ?? 'present',
                        'remark' => $row?->remark,
                    ];
                })
                ->values();
        }

        return view('school::print.attendance-sheet', array_merge([
            'year' => $year,
            'class' => $class,
            'date' => $date,
            'slot' => $slot,
            'roster' => $roster,
            'statuses' => SchoolAttendanceRecord::statuses(),
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
        ], PrintDocument::context(
            $request,
            'feuille-presence',
            ($class?->name ?? 'classe').'-'.$date,
            'tenant.school.attendance.index'
        )));
    }
}
