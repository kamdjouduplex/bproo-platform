<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolGradingRule;
use School\Models\SchoolGradingSystem;

class SchoolGradingRulesIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $filterYearId = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $gradingSystemId = null;
    public float $passMark = 10;
    public float $promotionAverage = 10;
    public ?int $maxFailedSubjects = null;
    public string $rankingMethod = 'weighted_average';
    public bool $absentCountsAsZero = true;
    public bool $requireValidatedMarks = true;
    public bool $isActive = true;

    public function updatedFilterYearId(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->openCreateForm();
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $this->gradingSystemId = SchoolGradingSystem::query()->where('is_active', true)->value('id');
        $system = $this->gradingSystemId
            ? SchoolGradingSystem::query()->find($this->gradingSystemId)
            : null;
        $base = (float) ($system?->scale_base ?? 20);
        $this->passMark = $base / 2;
        $this->promotionAverage = $base / 2;
        $this->rankingMethod = 'weighted_average';
        $this->absentCountsAsZero = true;
        $this->requireValidatedMarks = true;
        $this->isActive = true;
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = $this->classId = $this->gradingSystemId = null;
        $this->passMark = 10;
        $this->promotionAverage = 10;
        $this->maxFailedSubjects = null;
        $this->rankingMethod = 'weighted_average';
        $this->absentCountsAsZero = true;
        $this->requireValidatedMarks = true;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'gradingSystemId' => ['nullable', 'integer', Rule::exists(SchoolGradingSystem::class, 'id')],
            'passMark' => ['required', 'numeric', 'min:0'],
            'promotionAverage' => ['required', 'numeric', 'min:0'],
            'maxFailedSubjects' => ['nullable', 'integer', 'min:0'],
            'rankingMethod' => ['required', 'in:weighted_average,simple_average'],
            'absentCountsAsZero' => ['boolean'],
            'requireValidatedMarks' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        SchoolGradingRule::query()->updateOrCreate(
            [
                'academic_year_id' => $this->academicYearId,
                'class_id' => $this->classId,
            ],
            [
                'grading_system_id' => $this->gradingSystemId,
                'pass_mark' => $this->passMark,
                'promotion_average' => $this->promotionAverage,
                'max_failed_subjects' => $this->maxFailedSubjects,
                'ranking_method' => $this->rankingMethod,
                'absent_counts_as_zero' => $this->absentCountsAsZero,
                'require_validated_marks' => $this->requireValidatedMarks,
                'is_active' => $this->isActive,
            ]
        );
        notify()->success('Règle de calcul enregistrée.');
        $this->cancel();
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $systems = SchoolGradingSystem::query()->where('is_active', true)->orderBy('name')->get();

        $rules = SchoolGradingRule::query()
            ->with(['academicYear', 'schoolClass', 'gradingSystem'])
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->orderByDesc('academic_year_id')
            ->paginate(12);

        return view('school::livewire.school.grading.rules-index', [
            'rules' => $rules,
            'years' => $years,
            'classes' => $classes,
            'systems' => $systems,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Règles de calcul',
            'subtitle' => 'Passage, promotion, classement — sans logique codée en dur.',
        ]);
    }
}
