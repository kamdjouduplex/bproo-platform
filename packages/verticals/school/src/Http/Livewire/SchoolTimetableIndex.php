<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolCourse;
use School\Models\SchoolRoom;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Models\SchoolTimetableSlot;
use School\Support\SchoolTimetable;

class SchoolTimetableIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;

    public string $yearId = '';

    public string $viewMode = 'class';

    public string $classId = '';

    public string $teacherId = '';

    public bool $showSlotForm = false;

    public ?int $editingSlotId = null;

    public int $weekday = 1;

    public string $startTime = '07:30';

    public string $endTime = '08:20';

    public string $courseId = '';

    public string $roomId = '';

    public string $newSubjectId = '';

    public string $newTeacherId = '';

    public function mount(): void
    {
        if (! $this->canSchool('school_timetable.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->yearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
        $this->classId = (string) (request()->query('class') ?: SchoolClass::query()->where('is_active', true)->orderBy('name')->value('id') ?: '');
        $this->teacherId = (string) request()->query('teacher', '');
        if ($this->teacherId !== '') {
            $this->viewMode = 'teacher';
        }
    }

    public function updatedViewMode(): void
    {
        $this->closeSlotForm();
    }

    public function openCell(int $weekday, string $start, string $end, int $slotId = 0): void
    {
        $resolvedId = $slotId > 0 ? $slotId : null;

        if (! $this->canSchool('school_timetable.manage') && ! $resolvedId) {
            return;
        }

        $this->weekday = $weekday;
        $this->startTime = $start;
        $this->endTime = $end;
        $this->roomId = '';
        $this->newSubjectId = '';
        $this->newTeacherId = '';
        $this->editingSlotId = $resolvedId;
        $this->courseId = '';

        if ($resolvedId) {
            $slot = SchoolTimetableSlot::query()->with(['course.schoolRoom', 'schoolRoom'])->find($resolvedId);
            if ($slot) {
                $this->courseId = (string) $slot->course_id;
                $this->weekday = (int) $slot->weekday;
                $this->startTime = $slot->startHm();
                $this->endTime = $slot->endHm();
                $this->roomId = (string) ($slot->room_id ?: $slot->course?->room_id ?: '');
            }
        }

        $this->showSlotForm = true;
        $this->resetErrorBag();
    }

    public function editSlot(int $id): void
    {
        $this->openCell(1, '07:30', '08:20', $id);
    }

    public function closeSlotForm(): void
    {
        $this->showSlotForm = false;
        $this->editingSlotId = null;
        $this->courseId = '';
        $this->newSubjectId = '';
        $this->newTeacherId = '';
        $this->roomId = '';
        $this->resetErrorBag();
    }

    public function updatedCourseId(): void
    {
        if ($this->courseId === '') {
            return;
        }
        $course = SchoolCourse::query()->find((int) $this->courseId);
        if ($course?->room_id) {
            $this->roomId = (string) $course->room_id;
        }
    }

    public function saveSlot(): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }

        $this->validate([
            'weekday' => ['required', 'integer', 'min:1', 'max:6'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'roomId' => ['nullable'],
        ]);

        $course = $this->resolveCourse();
        if (! $course) {
            return;
        }

        $start = $this->startTime;
        $end = $this->endTime;
        $room = $this->roomId !== '' ? SchoolRoom::query()->find((int) $this->roomId) : null;

        $conflict = $this->conflictMessage(
            (int) $course->id,
            (int) $course->class_id,
            (int) $course->teacher_id,
            $start,
            $end,
            $room?->id
        );
        if ($conflict) {
            notify()->error($conflict);

            return;
        }

        $payload = [
            'course_id' => $course->id,
            'weekday' => (int) $this->weekday,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'room_id' => $room?->id,
            'room' => $room?->name,
        ];

        if ($this->editingSlotId) {
            SchoolTimetableSlot::query()->findOrFail($this->editingSlotId)->update($payload);
            notify()->success('Créneau mis à jour.');
        } else {
            SchoolTimetableSlot::query()->create($payload);
            notify()->success('Cours placé dans l’emploi du temps.');
        }

        $this->closeSlotForm();
    }

    public function deleteSlot(int $id): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }

        SchoolTimetableSlot::query()->where('id', $id)->delete();
        $this->closeSlotForm();
        notify()->success('Créneau retiré.');
    }

    protected function resolveCourse(): ?SchoolCourse
    {
        if ($this->courseId !== '') {
            return SchoolCourse::query()->find((int) $this->courseId);
        }

        if ($this->viewMode !== 'class' || $this->classId === '' || $this->yearId === '') {
            notify()->error('Choisissez un cours, ou créez-en un (matière + enseignant).');

            return null;
        }

        if ($this->newSubjectId === '' || $this->newTeacherId === '') {
            notify()->error('Choisissez un cours existant, ou une matière et un enseignant.');

            return null;
        }

        $existing = SchoolCourse::query()
            ->where('academic_year_id', (int) $this->yearId)
            ->where('class_id', (int) $this->classId)
            ->where('subject_id', (int) $this->newSubjectId)
            ->first();

        if ($existing) {
            $existing->update([
                'teacher_id' => (int) $this->newTeacherId,
                'is_active' => true,
            ]);

            return $existing;
        }

        return SchoolCourse::query()->create([
            'academic_year_id' => (int) $this->yearId,
            'class_id' => (int) $this->classId,
            'subject_id' => (int) $this->newSubjectId,
            'teacher_id' => (int) $this->newTeacherId,
            'room_id' => $this->roomId !== '' ? (int) $this->roomId : null,
            'room' => $this->roomId !== '' ? SchoolRoom::query()->where('id', (int) $this->roomId)->value('name') : null,
            'color' => SchoolTimetable::colorFor((int) $this->newSubjectId),
            'is_active' => true,
        ]);
    }

    protected function conflictMessage(int $courseId, int $classId, int $teacherId, string $start, string $end, ?int $roomId = null): ?string
    {
        $slots = SchoolTimetableSlot::query()
            ->with(['course.subject', 'course.teacher', 'course.schoolClass', 'schoolRoom', 'course.schoolRoom'])
            ->where('weekday', $this->weekday)
            ->when($this->editingSlotId, fn ($q) => $q->where('id', '!=', $this->editingSlotId))
            ->whereHas('course', fn ($q) => $q->where('academic_year_id', (int) $this->yearId)->where('is_active', true))
            ->get();

        foreach ($slots as $slot) {
            if (! SchoolTimetable::timesOverlap($start, $end, $slot->startHm(), $slot->endHm())) {
                continue;
            }
            if ((int) $slot->course?->class_id === $classId) {
                return 'La classe a déjà un cours à cette heure ('.$slot->course?->subject?->name.').';
            }
            if ((int) $slot->course?->teacher_id === $teacherId) {
                return ($slot->course?->teacher?->full_name ?? 'Cet enseignant').' enseigne déjà '.$slot->course?->schoolClass?->name.' à cette heure.';
            }
            $slotRoomId = (int) ($slot->room_id ?: $slot->course?->room_id ?: 0);
            if ($roomId && $slotRoomId === $roomId) {
                $roomName = $slot->roomLabel() ?: 'Cette salle';

                return $roomName.' est déjà occupée à cette heure ('.$slot->course?->subject?->name.' — '.$slot->course?->schoolClass?->name.').';
            }
        }

        return null;
    }

    public function printUrl(): string
    {
        return route('tenant.school.timetable.print', array_filter([
            'tenant' => $this->tenantCode(),
            'year' => $this->yearId,
            'view' => $this->viewMode,
            'class' => $this->classId,
            'teacher' => $this->teacherId,
        ], fn ($v) => $v !== '' && $v !== null));
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $teachers = SchoolTeacher::query()->where('is_active', true)->orderBy('full_name')->get();
        $subjects = SchoolSubject::query()->where('is_active', true)->orderBy('name')->get();
        $weekdays = SchoolTimetable::weekdays();

        $coursesQuery = SchoolCourse::query()
            ->with(['subject', 'teacher', 'schoolClass', 'schoolRoom'])
            ->where('is_active', true)
            ->when($this->yearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->yearId));

        if ($this->viewMode === 'teacher' && $this->teacherId !== '') {
            $coursesQuery->where('teacher_id', (int) $this->teacherId);
        } elseif ($this->classId !== '') {
            $coursesQuery->where('class_id', (int) $this->classId);
        } else {
            $coursesQuery->whereRaw('1 = 0');
        }

        $courses = $coursesQuery->orderBy('id')->get();
        $courseIds = $courses->pluck('id');

        $slots = $courseIds->isEmpty()
            ? collect()
            : SchoolTimetableSlot::query()
                ->with(['course.subject', 'course.teacher', 'course.schoolClass', 'course.schoolRoom', 'schoolRoom'])
                ->whereIn('course_id', $courseIds)
                ->orderBy('start_time')
                ->get();

        $built = SchoolTimetable::gridFromSlots($slots);
        $periods = $built['periods'];
        $grid = $built['grid'];

        $title = 'Emploi du temps';
        if ($this->viewMode === 'teacher') {
            $title .= ' — '.($teachers->firstWhere('id', (int) $this->teacherId)?->full_name ?? 'Enseignant');
        } else {
            $title .= ' — '.($classes->firstWhere('id', (int) $this->classId)?->name ?? 'Classe');
        }

        $schedule = SchoolTimetable::daySchedule();

        return view('school::livewire.school.timetable.index', [
            'years' => $years,
            'classes' => $classes,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'rooms' => SchoolRoom::activeList(),
            'courses' => $courses,
            'weekdays' => $weekdays,
            'periods' => $periods,
            'grid' => $grid,
            'slotsCount' => $slots->count(),
            'canManage' => $this->canSchool('school_timetable.manage'),
            'tenantCode' => $this->tenantCode(),
            'printUrl' => $this->printUrl(),
            'pageTitle' => $title,
        ])->layout('layouts.app', [
            'title' => $title,
            'subtitle' => 'Journée '.$schedule['start'].'–'.$schedule['end'].' · 1ère pause '.$schedule['break1'].' min · 2e pause '.$schedule['break2'].' min. Cliquez une case pour placer un cours.',
        ]);
    }
}
