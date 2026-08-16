<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolPublicationRule;

class SchoolPublicationRulesDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $ruleId;
    public string $mode = 'show';
    public string $name = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public bool $requireFeesPaid = false;
    public ?float $minFeesAmount = null;
    public bool $requireValidatedMarks = true;
    public bool $requireDirectorApproval = true;
    public bool $isActive = true;
    public ?string $description = null;

    public function mount(int $id): void
    {
        $this->ruleId = $id;
        SchoolPublicationRule::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $r = $this->entity();
        $this->name = $r->name;
        $this->academicYearId = $r->academic_year_id;
        $this->classId = $r->class_id;
        $this->requireFeesPaid = (bool) $r->require_fees_paid;
        $this->minFeesAmount = $r->min_fees_amount;
        $this->requireValidatedMarks = (bool) $r->require_validated_marks;
        $this->requireDirectorApproval = (bool) $r->require_director_approval;
        $this->isActive = (bool) $r->is_active;
        $this->description = $r->description;
        $this->openEditForm($r->id);
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->academicYearId = $this->classId = null;
        $this->requireFeesPaid = false;
        $this->minFeesAmount = null;
        $this->requireValidatedMarks = true;
        $this->requireDirectorApproval = true;
        $this->isActive = true;
        $this->description = null;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'academicYearId' => ['nullable', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'requireFeesPaid' => ['boolean'],
            'minFeesAmount' => ['nullable', 'numeric', 'min:0'],
            'requireValidatedMarks' => ['boolean'],
            'requireDirectorApproval' => ['boolean'],
            'isActive' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);
        $this->entity()->update([
            'name' => $this->name,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'require_fees_paid' => $this->requireFeesPaid,
            'min_fees_amount' => $this->minFeesAmount,
            'require_validated_marks' => $this->requireValidatedMarks,
            'require_director_approval' => $this->requireDirectorApproval,
            'is_active' => $this->isActive,
            'description' => filled($this->description) ? $this->description : null,
        ]);
        notify()->success('Règle mise à jour.');
        $this->cancel();
    }

    public function activate(): void { $this->entity()->update(['is_active' => true]); notify()->success('Règle activée.'); }
    public function deactivate(): void { $this->entity()->update(['is_active' => false]); notify()->success('Règle désactivée.'); }

    protected function entity(): SchoolPublicationRule
    {
        return SchoolPublicationRule::query()->with(['academicYear', 'schoolClass'])->findOrFail($this->ruleId);
    }

    public function render()
    {
        $rule = $this->entity();
        $isManage = $this->mode === 'manage';
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();

        return view('school::livewire.school.publications.rules-detail', [
            'rule' => $rule,
            'isManage' => $isManage,
            'years' => $years,
            'classes' => $classes,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$rule->name,
            'subtitle' => 'Conditions de publication des résultats.',
        ]);
    }
}
