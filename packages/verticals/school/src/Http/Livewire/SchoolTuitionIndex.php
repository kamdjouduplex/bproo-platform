<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Support\StudentLedgerService;

/**
 * Oversight of student tuition: fully paid / partial / unpaid for an academic year.
 */
class SchoolTuitionIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';

    public string $filterYearId = '';

    public string $filterClassId = '';

    /** paid | partial | unpaid | none | '' */
    public string $filterStatus = '';

    public function mount(): void
    {
        if (! $this->canSchool('school_payments.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->filterYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id')
            ?? '');
        $status = (string) request()->query('status', '');
        if (in_array($status, ['paid', 'partial', 'unpaid', 'none'], true)) {
            $this->filterStatus = $status;
        }
    }

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

    public function syncFeesForVisible(): void
    {
        if ($this->filterYearId === '') {
            notify()->error('Choisissez une année académique.');

            return;
        }

        $yearId = (int) $this->filterYearId;
        $ledger = app(StudentLedgerService::class);
        $n = 0;

        SchoolEnrollment::query()
            ->where('academic_year_id', $yearId)
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($ledger, $yearId, &$n) {
                foreach ($rows as $enrollment) {
                    $n += $ledger->chargeFeesForEnrollment(
                        (int) $enrollment->student_id,
                        $yearId,
                        $enrollment->class_id ? (int) $enrollment->class_id : null
                    );
                }
            });

        notify()->success($n > 0
            ? "{$n} frais imputé(s) au ledger."
            : 'Aucun nouveau frais à imputer (déjà à jour).');
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $ledger = app(StudentLedgerService::class);
        $term = trim($this->search);
        $yearId = $this->filterYearId !== '' ? (int) $this->filterYearId : null;

        $enrollments = SchoolEnrollment::query()
            ->with(['student', 'academicYear', 'schoolClass'])
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereHas('student', function ($sq) use ($like) {
                    $sq->whereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_full_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_phone, \'\')) LIKE ?', [$like]);
                });
            })
            ->orderByDesc('id')
            ->get();

        $rows = $enrollments->map(function (SchoolEnrollment $e) use ($ledger) {
            $snap = $e->academic_year_id
                ? $ledger->tuitionSnapshot((int) $e->student_id, (int) $e->academic_year_id)
                : ['charged' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'status' => 'none'];

            return [
                'enrollment' => $e,
                'student' => $e->student,
                'charged' => $snap['charged'],
                'paid' => $snap['paid'],
                'due' => $snap['due'],
                'status' => $snap['status'],
            ];
        });

        if ($this->filterStatus !== '') {
            $rows = $rows->filter(fn ($r) => $r['status'] === $this->filterStatus)->values();
        }

        $counts = [
            'paid' => $rows->where('status', 'paid')->count(),
            'partial' => $rows->where('status', 'partial')->count(),
            'unpaid' => $rows->where('status', 'unpaid')->count(),
            'none' => $rows->where('status', 'none')->count(),
            'due_total' => round((float) $rows->sum('due'), 0),
        ];

        // Manual pagination of collection
        $page = max(1, (int) $this->getPage());
        $perPage = 20;
        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('school::livewire.school.tuition.index', [
            'years' => $years,
            'classes' => $classes,
            'rows' => $paginator,
            'counts' => $counts,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Soldes scolarité',
            'subtitle' => 'Élèves à jour, partiels ou impayés — vue d’ensemble.',
        ]);
    }
}
