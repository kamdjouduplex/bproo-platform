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
use School\Models\SchoolResultPublication;

class SchoolPublicationsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $filterYearId = '';
    public string $filterStatus = '';

    public string $title = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $publicationRuleId = null;
    public ?string $notes = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterYearId(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->openCreateForm();
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $this->publicationRuleId = SchoolPublicationRule::query()->where('is_active', true)->value('id');
        $this->title = 'Publication résultats';
    }

    protected function resetFormFields(): void
    {
        $this->title = '';
        $this->academicYearId = $this->classId = $this->publicationRuleId = null;
        $this->notes = null;
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'publicationRuleId' => ['nullable', 'integer', Rule::exists(SchoolPublicationRule::class, 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        SchoolResultPublication::query()->create([
            'title' => $this->title,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'publication_rule_id' => $this->publicationRuleId,
            'status' => 'draft',
            'notes' => filled($this->notes) ? $this->notes : null,
        ]);
        notify()->success('Publication créée (brouillon).');
        $this->cancel();
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $rules = SchoolPublicationRule::query()->where('is_active', true)->orderBy('name')->get();
        $term = trim($this->search);

        $rows = SchoolResultPublication::query()
            ->with(['academicYear', 'schoolClass', 'rule'])
            ->withCount(['lines', 'lines as eligible_count' => fn ($q) => $q->where('eligible', true)])
            ->when($term !== '', fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($term).'%']))
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->orderByDesc('id')
            ->paginate(12);

        return view('school::livewire.school.publications.index', [
            'rows' => $rows,
            'years' => $years,
            'classes' => $classes,
            'rules' => $rules,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Publication des résultats',
            'subtitle' => 'Publication conditionnée par des règles métier configurables.',
        ]);
    }
}
