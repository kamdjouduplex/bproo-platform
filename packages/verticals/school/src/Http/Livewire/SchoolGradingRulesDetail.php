<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Http\Livewire\Concerns\SearchesStudents;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolGradingRule;
use School\Models\SchoolGradingSystem;
use School\Models\SchoolStudent;
use School\Support\GradingCalculator;

class SchoolGradingRulesDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;

    public int $ruleId;
    public string $mode = 'show';

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

    public ?array $preview = null;

    public function mount(int $id): void
    {
        $this->ruleId = $id;
        SchoolGradingRule::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $rule = $this->entity();
        $this->academicYearId = $rule->academic_year_id;
        $this->classId = $rule->class_id;
        $this->gradingSystemId = $rule->grading_system_id;
        $this->passMark = (float) $rule->pass_mark;
        $this->promotionAverage = (float) $rule->promotion_average;
        $this->maxFailedSubjects = $rule->max_failed_subjects;
        $this->rankingMethod = $rule->ranking_method;
        $this->absentCountsAsZero = (bool) $rule->absent_counts_as_zero;
        $this->requireValidatedMarks = (bool) $rule->require_validated_marks;
        $this->isActive = (bool) $rule->is_active;
        $this->openEditForm($rule->id);
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

        $this->entity()->update([
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'grading_system_id' => $this->gradingSystemId,
            'pass_mark' => $this->passMark,
            'promotion_average' => $this->promotionAverage,
            'max_failed_subjects' => $this->maxFailedSubjects,
            'ranking_method' => $this->rankingMethod,
            'absent_counts_as_zero' => $this->absentCountsAsZero,
            'require_validated_marks' => $this->requireValidatedMarks,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Règle mise à jour.');
        $this->cancel();
    }

    public function runPreview(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
        ]);
        $rule = $this->entity();
        $this->preview = app(GradingCalculator::class)->computeForStudent(
            (int) $this->studentId,
            (int) $rule->academic_year_id,
            $rule->class_id ? (int) $rule->class_id : null
        );
    }

    protected function entity(): SchoolGradingRule
    {
        return SchoolGradingRule::query()
            ->with(['academicYear', 'schoolClass', 'gradingSystem.scales'])
            ->findOrFail($this->ruleId);
    }

    public function render()
    {
        $rule = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $systems = SchoolGradingSystem::query()->where('is_active', true)->orderBy('name')->get();

        return view('school::livewire.school.grading.rules-detail', [
            'rule' => $rule,
            'isManage' => $isManage,
            'years' => $years,
            'classes' => $classes,
            'systems' => $systems,
            'studentResults' => $this->studentSearchResults(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').'Règle de calcul',
            'subtitle' => ($rule->academicYear?->name ?? '').($rule->schoolClass ? ' · '.$rule->schoolClass->name : ' · Toutes classes'),
        ]);
    }
}
