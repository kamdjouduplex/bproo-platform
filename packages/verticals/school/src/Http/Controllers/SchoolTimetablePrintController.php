<?php

namespace School\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use School\Http\Controllers\Concerns\AuthorizesSchoolHttp;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolCourse;
use School\Models\SchoolTeacher;
use School\Models\SchoolTimetableSlot;
use School\Support\SchoolTimetable;

class SchoolTimetablePrintController
{
    use AuthorizesSchoolHttp;

    public function __invoke(Request $request): View
    {
        $this->authorizeSchoolPermission('school_timetable.view');

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $yearId = $request->integer('year');
        $view = $request->query('view', 'class') === 'teacher' ? 'teacher' : 'class';
        $classId = $request->integer('class');
        $teacherId = $request->integer('teacher');

        $year = $yearId ? AcademicYear::query()->find($yearId) : AcademicYear::query()->where('is_active', true)->first();
        $class = $classId ? SchoolClass::query()->find($classId) : null;
        $teacher = $teacherId ? SchoolTeacher::query()->find($teacherId) : null;

        $coursesQuery = SchoolCourse::query()
            ->with(['subject', 'teacher', 'schoolClass'])
            ->where('is_active', true)
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id));

        if ($view === 'teacher' && $teacher) {
            $coursesQuery->where('teacher_id', $teacher->id);
            $title = 'Emploi du temps — '.($teacher->full_name ?? 'Enseignant');
        } elseif ($class) {
            $coursesQuery->where('class_id', $class->id);
            $title = 'Emploi du temps — '.($class->name ?? 'Classe');
        } else {
            $coursesQuery->whereRaw('1 = 0');
            $title = 'Emploi du temps';
        }

        $courses = $coursesQuery->get();
        $slots = $courses->isEmpty()
            ? collect()
            : SchoolTimetableSlot::query()
                ->with(['course.subject', 'course.teacher', 'course.schoolClass', 'course.schoolRoom', 'schoolRoom'])
                ->whereIn('course_id', $courses->pluck('id'))
                ->orderBy('start_time')
                ->get();

        $built = SchoolTimetable::gridFromSlots($slots);

        return view('school::print.timetable', array_merge([
            'title' => $title,
            'year' => $year,
            'class' => $class,
            'teacher' => $teacher,
            'view' => $view,
            'courses' => $courses,
            'weekdays' => SchoolTimetable::weekdays(),
            'periods' => $built['periods'],
            'grid' => $built['grid'],
            'settings' => $settings,
            'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo School'),
        ], PrintDocument::context(
            $request,
            'emploi-du-temps',
            $view === 'teacher' ? ($teacher?->full_name ?? 'enseignant') : ($class?->name ?? 'classe'),
            'tenant.school.timetable.index'
        )));
    }
}
