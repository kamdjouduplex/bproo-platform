<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Support\AcademicYearCarryOver;

class SchoolYearsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';

    public string $filterActive = '';

    public string $code = '';

    public string $name = '';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public bool $isActive = false;

    public ?int $carryFromYearId = null;

    public bool $carryFees = true;

    public bool $carryGrading = true;

    public bool $carryCoefficients = true;

    public bool $carryExams = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterActive(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->openCreateForm();
    }

    protected function resetFormFields(): void
    {
        $this->code = '';
        $this->name = '';
        $this->startDate = null;
        $this->endDate = null;
        $this->isActive = false;
        $this->carryFromYearId = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->value('id');
        $this->carryFees = true;
        $this->carryGrading = true;
        $this->carryCoefficients = true;
        $this->carryExams = false;
    }

    public function save(): void
    {
        $this->validate([
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique(AcademicYear::class, 'code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'isActive' => ['boolean'],
            'carryFromYearId' => ['nullable', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'carryFees' => ['boolean'],
            'carryGrading' => ['boolean'],
            'carryCoefficients' => ['boolean'],
            'carryExams' => ['boolean'],
        ]);

        $payload = [
            'code' => $this->code !== '' ? $this->code : null,
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_active' => $this->isActive,
        ];

        $year = AcademicYear::query()->create($payload);

        if ($year->is_active) {
            AcademicYear::query()->where('id', '!=', $year->id)->update(['is_active' => false]);
        }

        $carryMsg = '';
        if ($this->carryFromYearId
            && ($this->carryFees || $this->carryGrading || $this->carryCoefficients || $this->carryExams)) {
            $source = AcademicYear::query()->find($this->carryFromYearId);
            if ($source) {
                $counts = app(AcademicYearCarryOver::class)->fromYear($source, $year, [
                    'fees' => $this->carryFees,
                    'grading' => $this->carryGrading,
                    'coefficients' => $this->carryCoefficients,
                    'exams' => $this->carryExams,
                ]);
                $parts = [];
                if ($counts['fees']) {
                    $parts[] = $counts['fees'].' frais';
                }
                if ($counts['grading']) {
                    $parts[] = $counts['grading'].' règles notation';
                }
                if ($counts['coefficients']) {
                    $parts[] = $counts['coefficients'].' coefficients';
                }
                if ($counts['exams']) {
                    $parts[] = $counts['exams'].' examens (brouillon)';
                }
                if ($parts !== []) {
                    $carryMsg = ' Réutilisation : '.implode(', ', $parts).'.';
                }
            }
        }

        notify()->success('Année académique ajoutée.'.$carryMsg);
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);

        $years = AcademicYear::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->paginate(12);

        $priorYears = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();

        return view('school::livewire.school.years.index', [
            'years' => $years,
            'priorYears' => $priorYears,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Années académiques',
            'subtitle' => 'Gérer les années et leur activation.',
        ]);
    }
}
