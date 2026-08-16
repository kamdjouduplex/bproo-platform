<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolSubject;
use School\Models\SchoolSubjectCoefficient;

class SchoolCoefficientsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $filterYearId = '';
    public string $filterClassId = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $subjectId = null;
    public float $coefficient = 1;

    public function updatedFilterYearId(): void { $this->resetPage(); }
    public function updatedFilterClassId(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->openCreateForm();
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $this->coefficient = 1;
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = $this->classId = $this->subjectId = null;
        $this->coefficient = 1;
    }

    public function save(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'subjectId' => ['required', 'integer', Rule::exists(SchoolSubject::class, 'id')],
            'coefficient' => ['required', 'numeric', 'min:0'],
        ]);

        SchoolSubjectCoefficient::query()->updateOrCreate(
            [
                'academic_year_id' => $this->academicYearId,
                'class_id' => $this->classId,
                'subject_id' => $this->subjectId,
            ],
            ['coefficient' => $this->coefficient]
        );
        notify()->success('Coefficient enregistré.');
        $this->cancel();
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $subjects = SchoolSubject::query()->where('is_active', true)->orderBy('name')->get();

        $rows = SchoolSubjectCoefficient::query()
            ->with(['academicYear', 'schoolClass', 'subject'])
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->orderByDesc('academic_year_id')
            ->orderBy('class_id')
            ->paginate(20);

        return view('school::livewire.school.grading.coefficients-index', [
            'rows' => $rows,
            'years' => $years,
            'classes' => $classes,
            'subjects' => $subjects,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Coefficients matières',
            'subtitle' => 'Pondération des matières par année et classe.',
        ]);
    }
}
