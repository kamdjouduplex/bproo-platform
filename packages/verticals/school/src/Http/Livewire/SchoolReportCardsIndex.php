<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Support\GradingCalculator;

class SchoolReportCardsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $filterYearId = '';
    public string $filterClassId = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterYearId(): void { $this->resetPage(); }
    public function updatedFilterClassId(): void { $this->resetPage(); }

    public function mount(): void
    {
        if (! $this->canSchool('school_report_cards.view') && ! $this->canSchool('school_report_cards.print')) {
            abort(403, 'Permission refusée.');
        }

        $this->filterYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $term = trim($this->search);
        $calculator = app(GradingCalculator::class);

        $rows = SchoolEnrollment::query()
            ->with(['student', 'academicYear', 'schoolClass'])
            ->where('status', 'enrolled')
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereHas('student', function ($s) use ($like) {
                    $s->whereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(parent_full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(parent_phone) LIKE ?', [$like]);
                });
            })
            ->orderByDesc('id')
            ->paginate(15);

        $averages = [];
        foreach ($rows as $row) {
            $result = $calculator->computeForStudent(
                (int) $row->student_id,
                (int) $row->academic_year_id,
                $row->class_id ? (int) $row->class_id : null
            );
            $averages[$row->id] = $result;
        }

        return view('school::livewire.school.report-cards.index', [
            'rows' => $rows,
            'years' => $years,
            'classes' => $classes,
            'averages' => $averages,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Bulletins & relevés',
            'subtitle' => 'Aperçu, impression et PDF (navigateur).',
        ]);
    }
}
