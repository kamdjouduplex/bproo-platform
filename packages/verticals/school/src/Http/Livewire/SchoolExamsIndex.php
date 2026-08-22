<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolExam;
use School\Models\SchoolSubject;
use School\Models\SchoolTeacher;
use School\Support\SchoolExamCatalog;
use School\Support\SchoolOptionCatalog;

class SchoolExamsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $filterYearId = '';
    public string $filterClassId = '';
    public string $filterSubjectId = '';
    public string $filterStatus = '';
    public string $filterKind = '';
    public string $filterPeriod = '';

    public ?int $academicYearId = null;
    public ?int $classId = null;
    public ?int $subjectId = null;
    public ?int $teacherId = null;
    public string $title = '';
    public string $kind = 'devoir';
    public string $period = 'seq_1';
    public string $lastSuggestedTitle = '';
    public ?string $examDate = null;
    public float $maxScore = 20;
    public float $coefficient = 1;
    public string $status = 'draft';
    public ?string $notes = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterYearId(): void { $this->resetPage(); }
    public function updatedFilterClassId(): void { $this->resetPage(); }
    public function updatedFilterSubjectId(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterKind(): void { $this->resetPage(); }
    public function updatedFilterPeriod(): void { $this->resetPage(); }

    public function mount(): void
    {
        try {
            SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
        }
    }

    public function updatedKind(): void
    {
        $this->suggestTitleIfEmpty();
    }

    public function updatedPeriod(): void
    {
        $this->suggestTitleIfEmpty();
    }

    public function create(): void
    {
        $this->openCreateForm();
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $this->kind = 'devoir';
        $this->period = 'seq_1';
        $this->maxScore = 20;
        $this->coefficient = 1;
        $this->status = 'draft';
        $this->title = '';
        $this->lastSuggestedTitle = '';
        $this->suggestTitleIfEmpty();
    }

    protected function resetFormFields(): void
    {
        $this->academicYearId = $this->classId = $this->subjectId = $this->teacherId = null;
        $this->title = '';
        $this->kind = 'devoir';
        $this->period = 'seq_1';
        $this->lastSuggestedTitle = '';
        $this->examDate = null;
        $this->maxScore = 20;
        $this->coefficient = 1;
        $this->status = 'draft';
        $this->notes = null;
    }

    protected function suggestTitleIfEmpty(): void
    {
        $suggested = SchoolExamCatalog::suggestTitle($this->kind, $this->period);
        if (trim($this->title) === '' || $this->title === $this->lastSuggestedTitle) {
            $this->title = $suggested;
        }
        $this->lastSuggestedTitle = $suggested;
    }

    public function save(): void
    {
        $kindValues = SchoolExamCatalog::kindValues();
        $periodValues = SchoolExamCatalog::periodValues();

        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'classId' => ['required', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'subjectId' => ['required', 'integer', Rule::exists(SchoolSubject::class, 'id')],
            'teacherId' => ['nullable', 'integer', Rule::exists(SchoolTeacher::class, 'id')],
            'kind' => ['required', 'string', Rule::in($kindValues !== [] ? $kindValues : [$this->kind])],
            'period' => ['required', 'string', Rule::in($periodValues !== [] ? $periodValues : [$this->period])],
            'title' => ['required', 'string', 'max:255'],
            'examDate' => ['nullable', 'date'],
            'maxScore' => ['required', 'numeric', 'min:0.01'],
            'coefficient' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,open,closed'],
            'notes' => ['nullable', 'string'],
        ]);

        SchoolExam::query()->create([
            'academic_year_id' => $this->academicYearId,
            'class_id' => $this->classId,
            'subject_id' => $this->subjectId,
            'teacher_id' => $this->teacherId,
            'title' => $this->title,
            'kind' => $this->kind,
            'period' => $this->period,
            'exam_date' => $this->examDate,
            'max_score' => $this->maxScore,
            'coefficient' => $this->coefficient,
            'status' => $this->status,
            'notes' => filled($this->notes) ? $this->notes : null,
        ]);

        notify()->success('Examen créé.');
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $subjects = SchoolSubject::query()->where('is_active', true)->orderBy('name')->get();
        $teachers = SchoolTeacher::query()->where('is_active', true)->orderBy('full_name')->get();

        $exams = SchoolExam::query()
            ->with(['academicYear', 'schoolClass', 'subject', 'teacher'])
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when($this->filterClassId !== '', fn ($q) => $q->where('class_id', (int) $this->filterClassId))
            ->when($this->filterSubjectId !== '', fn ($q) => $q->where('subject_id', (int) $this->filterSubjectId))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterKind !== '', fn ($q) => $q->where('kind', $this->filterKind))
            ->when($this->filterPeriod !== '', fn ($q) => $q->where('period', $this->filterPeriod))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereRaw('LOWER(title) LIKE ?', [$like]);
            })
            ->orderByDesc('exam_date')
            ->orderByDesc('id')
            ->paginate(12);

        return view('school::livewire.school.exams.index', [
            'exams' => $exams,
            'years' => $years,
            'classes' => $classes,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'examKinds' => SchoolExamCatalog::kinds(),
            'examPeriods' => SchoolExamCatalog::periods(),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Examens',
            'subtitle' => 'Devoirs, séquences et compositions par classe et matière.',
        ]);
    }
}
