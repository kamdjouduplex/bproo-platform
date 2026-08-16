<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolFeeStructure;

class SchoolFeesIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $name = '';
    public ?int $academicYearId = null;
    public ?int $classId = null;
    public float $amount = 0;
    public string $currencyCode = 'XOF';
    public bool $isActive = true;
    public ?string $description = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function create(): void { $this->openCreateForm(); }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->academicYearId = $this->classId = null;
        $this->amount = 0;
        $this->currencyCode = 'XOF';
        $this->isActive = true;
        $this->description = null;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'academicYearId' => ['nullable', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'amount' => ['required', 'numeric', 'min:0'],
            'currencyCode' => ['required', 'string', 'max:10'],
            'isActive' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);

        SchoolFeeStructure::query()->create([
            'name' => $this->name,
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'amount' => $this->amount,
            'currency_code' => $this->currencyCode,
            'is_active' => $this->isActive,
            'description' => filled($this->description) ? $this->description : null,
        ]);
        notify()->success('Structure de frais créée.');
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $rows = SchoolFeeStructure::query()
            ->with(['academicYear', 'schoolClass'])
            ->when($term !== '', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']))
            ->orderByDesc('is_active')->orderBy('name')->paginate(12);

        return view('school::livewire.school.fees.index', [
            'rows' => $rows,
            'years' => $years,
            'classes' => $classes,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Structures de frais',
            'subtitle' => 'Barèmes configurables par année / classe.',
        ]);
    }
}
