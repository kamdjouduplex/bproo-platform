<?php

namespace School\Http\Livewire;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolStudent;
use School\Support\SchoolDocumentCatalog;

class SchoolDocumentsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';

    public string $classId = '';

    public string $filterIncomplete = '';

    public ?int $studentId = null;

    public function mount(): void
    {
        if (! $this->canSchool('school_documents.view')) {
            abort(403, 'Permission refusée.');
        }

        $sid = (int) request()->query('student', 0);
        $this->studentId = $sid > 0 ? $sid : null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClassId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterIncomplete(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenantCode = $this->tenantCode();
        $tableReady = $this->documentsTableReady();
        $required = SchoolDocumentCatalog::requiredValues();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();

        $students = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        if ($tableReady) {
            $term = trim($this->search);
            $students = SchoolStudent::query()
                ->with(['documents', 'currentEnrollment.schoolClass'])
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.mb_strtolower($term).'%';
                    $q->where(function ($inner) use ($like) {
                        $inner->whereRaw('LOWER(student_code) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(nisu, \'\')) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                            ->orWhereRaw("LOWER(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) LIKE ?", [$like]);
                    });
                })
                ->when($this->classId !== '', function ($q) {
                    $yearId = AcademicYear::query()->where('is_active', true)->value('id');
                    $q->whereHas('enrollments', function ($e) use ($yearId) {
                        $e->where('class_id', $this->classId)
                            ->when($yearId, fn ($qq) => $qq->where('academic_year_id', $yearId));
                    });
                })
                ->when($this->filterIncomplete === '1' && $required !== [], function ($q) use ($required) {
                    $q->where(function ($outer) use ($required) {
                        foreach ($required as $type) {
                            $outer->orWhereDoesntHave('documents', fn ($d) => $d->where('type', $type));
                        }
                    });
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(20)
                ->through(function (SchoolStudent $student) {
                    $student->setAttribute('document_coverage', SchoolDocumentCatalog::coverage($student->documents));

                    return $student;
                });
        }

        return view('school::livewire.school.documents.index', [
            'students' => $students,
            'classes' => $classes,
            'tableReady' => $tableReady,
            'tenantCode' => $tenantCode,
            'hasStudentShow' => Route::has('tenant.school.students.show'),
        ])->layout('layouts.app', [
            'title' => 'École — Pièces',
            'subtitle' => 'Documents fournis par chaque élève tout au long de son parcours.',
        ]);
    }

    protected function documentsTableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('school_student_documents');
        } catch (\Throwable) {
            return false;
        }
    }
}
