<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolPublicationRule;

class SchoolPublicationRulesIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $name = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public bool $requireFeesPaid = false;
    public ?float $minFeesAmount = null;
    public bool $requireValidatedMarks = true;
    public bool $requireDirectorApproval = true;
    public bool $isActive = true;
    public ?string $description = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function create(): void { $this->openCreateForm(); }

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

        SchoolPublicationRule::query()->create([
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
        notify()->success('Règle de publication créée.');
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $rows = SchoolPublicationRule::query()
            ->with(['academicYear', 'schoolClass'])
            ->when($term !== '', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']))
            ->orderByDesc('is_active')->orderBy('name')->paginate(12);

        return view('school::livewire.school.publications.rules-index', [
            'rows' => $rows,
            'years' => $years,
            'classes' => $classes,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Règles de publication',
            'subtitle' => 'Frais, notes validées, approbation directeur.',
        ]);
    }
}
