<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolCourse;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Support\SchoolTimetable;

class SchoolCoursesIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $yearId = '';

    public string $filterClassId = '';

    public string $filterTeacherId = '';

    public string $search = '';

    public string $classId = '';

    public string $subjectId = '';

    public string $teacherId = '';

    public bool $isActive = true;

    public function mount(): void
    {
        if (! $this->canSchool('school_timetable.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->yearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
    }

    public function updatedYearId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterClassId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterTeacherId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }
        $this->openCreateForm();
        $this->classId = $this->filterClassId;
    }

    public function edit(int $id): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }
        $course = SchoolCourse::query()->findOrFail($id);
        $this->classId = (string) $course->class_id;
        $this->subjectId = (string) $course->subject_id;
        $this->teacherId = (string) $course->teacher_id;
        $this->isActive = (bool) $course->is_active;
        $this->openEditForm($id);
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }

        $this->validate([
            'yearId' => ['required'],
            'classId' => ['required'],
            'subjectId' => ['required'],
            'teacherId' => ['required'],
        ]);

        $payload = [
            'academic_year_id' => (int) $this->yearId,
            'class_id' => (int) $this->classId,
            'subject_id' => (int) $this->subjectId,
            'teacher_id' => (int) $this->teacherId,
            'is_active' => $this->isActive,
            'color' => SchoolTimetable::colorFor((int) $this->subjectId),
        ];

        $query = SchoolCourse::query()
            ->where('academic_year_id', $payload['academic_year_id'])
            ->where('class_id', $payload['class_id'])
            ->where('subject_id', $payload['subject_id']);
        if ($this->editingId) {
            $query->where('id', '!=', $this->editingId);
        }
        if ($query->exists()) {
            notify()->error('Cette matière est déjà attribuée à un enseignant dans cette classe.');

            return;
        }

        if ($this->editingId) {
            SchoolCourse::query()->findOrFail($this->editingId)->update($payload);
            notify()->success('Cours mis à jour.');
        } else {
            SchoolCourse::query()->create($payload);
            notify()->success('Cours créé. Placez-le ensuite dans l’emploi du temps.');
        }

        $this->cancel();
    }

    public function delete(int $id): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }

        $course = SchoolCourse::query()->findOrFail($id);
        $course->delete();
        notify()->success('Cours retiré (créneaux associés aussi).');
    }

    protected function resetFormFields(): void
    {
        $this->classId = $this->filterClassId;
        $this->subjectId = '';
        $this->teacherId = '';
        $this->isActive = true;
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $teachers = SchoolTeacher::query()->where('is_active', true)->orderBy('full_name')->get();
        $subjects = SchoolSubject::query()->where('is_active', true)->orderBy('name')->get();
        $term = trim($this->search);

        $courses = SchoolCourse::query()
            ->with(['schoolClass', 'subject', 'teacher', 'academicYear', 'slots'])
            ->when($this->yearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->yearId))
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->when($this->filterTeacherId !== '', fn ($q) => $q->where('teacher_id', (int) $this->filterTeacherId))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereHas('subject', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$like]))
                        ->orWhereHas('teacher', fn ($t) => $t->whereRaw('LOWER(full_name) LIKE ?', [$like]))
                        ->orWhereHas('schoolClass', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$like]));
                });
            })
            ->orderBy('class_id')
            ->paginate(20);

        return view('school::livewire.school.courses.index', [
            'years' => $years,
            'classes' => $classes,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'courses' => $courses,
            'canManage' => $this->canSchool('school_timetable.manage'),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Cours',
            'subtitle' => 'Qui enseigne quelle matière, dans quelle classe.',
        ]);
    }
}
