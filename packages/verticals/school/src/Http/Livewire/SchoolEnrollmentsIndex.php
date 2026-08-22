<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Http\Livewire\Concerns\SearchesStudents;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Support\SchoolOptionCatalog;
use School\Support\StudentLedgerService;

class SchoolEnrollmentsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;
    use WithPagination;

    public string $search = '';

    public string $filterYearId = '';

    public string $filterClassId = '';

    public string $filterStatus = '';

    public ?int $academicYearId = null;

    public ?int $classId = null;

    public ?string $section = null;

    public string $status = 'enrolled';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYearId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterClassId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->openCreateForm();
        $this->clearStudent();
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $statuses = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS);
        if ($statuses->isNotEmpty()) {
            $this->status = (string) $statuses->first()->value;
        }
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = null;
        $this->classId = null;
        $this->section = null;
        $this->status = 'enrolled';
        $this->clearStudent();
    }

    public function save(): void
    {
        $sectionValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION)->pluck('value')->all();
        $statusValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS)->pluck('value')->all();

        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
            'section' => ['nullable', 'string', 'max:80', Rule::in(array_merge([''], $sectionValues))],
            'status' => ['required', 'string', 'max:80', Rule::in($statusValues !== [] ? $statusValues : [$this->status])],
        ]);

        $payload = [
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'section' => $this->section !== '' && $this->section !== null ? $this->section : null,
            'status' => $this->status,
        ];

        SchoolEnrollment::query()->updateOrCreate(
            ['student_id' => $this->studentId, 'academic_year_id' => $this->academicYearId],
            ['class_id' => $this->classId, 'section' => $payload['section'], 'status' => $this->status]
        );

        app(StudentLedgerService::class)->chargeFeesForEnrollment(
            (int) $this->studentId,
            (int) $this->academicYearId,
            $this->classId ? (int) $this->classId : null
        );

        $student = SchoolStudent::query()->find($this->studentId);
        if ($student) {
            app(\School\Support\SchoolNotificationDispatcher::class)->dispatch('enrollment', $student, [
                'year' => AcademicYear::query()->find($this->academicYearId)?->name,
                'class' => SchoolClass::query()->find($this->classId)?->name,
            ]);
        }

        notify()->success('Inscription enregistrée.');

        $this->cancel();
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderBy('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $sections = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION);
        $statuses = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS);
        $statusLabels = $statuses->pluck('label', 'value');

        $term = trim($this->search);

        $enrollments = SchoolEnrollment::query()
            ->with(['student', 'academicYear', 'schoolClass'])
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereHas('student', function ($sq) use ($like) {
                    $sq->whereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                });
            })
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->paginate(12);

        return view('school::livewire.school.enrollments.index', [
            'years' => $years,
            'classes' => $classes,
            'sections' => $sections,
            'statuses' => $statuses,
            'statusLabels' => $statusLabels,
            'enrollments' => $enrollments,
            'studentResults' => $this->studentSearchResults(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Inscriptions',
            'subtitle' => 'Inscription par année académique.',
        ]);
    }
}
