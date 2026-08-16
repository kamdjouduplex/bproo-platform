<?php

namespace School\Http\Livewire;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Http\Livewire\Concerns\SearchesStudents;
use School\Models\AcademicYear;
use School\Models\SchoolClass;
use School\Models\SchoolEnrollment;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Models\StudentIdCard;
use School\Support\SchoolOptionCatalog;

class SchoolIdCardsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use SearchesStudents;
    use WithPagination;

    public string $search = '';

    public string $filterYearId = '';

    public string $filterBatch = '';

    public string $listClassId = '';

    public string $listSection = '';

    public string $listGender = '';

    public string $listStatus = '';

    public string $mode = 'single';

    public ?int $academicYearId = null;

    public ?string $batchCode = null;

    public ?int $filterClassId = null;

    public ?string $filterSection = null;

    public ?string $filterGender = null;

    public string $filterStatus = 'enrolled';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYearId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterBatch(): void
    {
        $this->resetPage();
    }

    public function updatedListClassId(): void
    {
        $this->resetPage();
    }

    public function updatedListSection(): void
    {
        $this->resetPage();
    }

    public function updatedListGender(): void
    {
        $this->resetPage();
    }

    public function updatedListStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        if (! $this->authorizeSchool('school_id_cards.manage')) {
            return;
        }
        $this->openCreateForm();
        $this->clearStudent();
        $this->mode = 'single';
        $this->academicYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('id')->value('id');
        $this->batchCode = null;
    }

    protected function resetFormFields(): void
    {
        $this->mode = 'single';
        $this->academicYearId = null;
        $this->batchCode = null;
        $this->filterClassId = null;
        $this->filterSection = null;
        $this->filterGender = null;
        $this->filterStatus = 'enrolled';
        $this->clearStudent();
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_id_cards.manage')) {
            return;
        }

        if ($this->mode === 'batch' && ! $this->editingId) {
            $this->generateBatch();
            $this->showForm = false;

            return;
        }

        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'studentId' => ['required', 'integer', Rule::exists(SchoolStudent::class, 'id')],
            'batchCode' => ['nullable', 'string', 'max:120'],
        ]);

        $this->upsertCard(
            (int) $this->studentId,
            (int) $this->academicYearId,
            $this->batchCode !== '' && $this->batchCode !== null ? $this->batchCode : null
        );

        notify()->success($this->editingId ? 'Carte ID mise à jour.' : 'Carte ID générée.');
        $this->cancel();
    }

    public function generateBatch(): void
    {
        if (! $this->authorizeSchool('school_id_cards.manage')) {
            return;
        }

        $sectionValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION)->pluck('value')->all();
        $genderValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER)->pluck('value')->all();
        $statusValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS)->pluck('value')->all();

        $this->validate([
            'academicYearId' => ['required', 'integer', Rule::exists(AcademicYear::class, 'id')],
            'filterClassId' => ['nullable', 'integer', Rule::exists(SchoolClass::class, 'id')],
            'filterSection' => ['nullable', 'string', 'max:80', Rule::in(array_merge([''], $sectionValues))],
            'filterGender' => ['nullable', 'string', 'max:40', Rule::in(array_merge([''], $genderValues))],
            'filterStatus' => ['nullable', 'string', 'max:80', Rule::in(array_merge([''], $statusValues))],
            'batchCode' => ['nullable', 'string', 'max:120'],
        ]);

        $batch = $this->batchCode !== '' && $this->batchCode !== null
            ? $this->batchCode
            : ('LOT-'.now()->format('Ymd-Hi'));

        $enrollmentQuery = SchoolEnrollment::query()
            ->where('academic_year_id', $this->academicYearId)
            ->when($this->filterClassId, fn ($q) => $q->where('class_id', $this->filterClassId))
            ->when($this->filterSection !== null && $this->filterSection !== '', fn ($q) => $q->where('section', $this->filterSection))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus));

        $studentIds = $enrollmentQuery->pluck('student_id')->unique()->values();

        if ($studentIds->isEmpty()) {
            notify()->error('Aucun élève trouvé pour ces filtres.');

            return;
        }

        $students = SchoolStudent::query()
            ->whereIn('id', $studentIds)
            ->where('is_active', true)
            ->when($this->filterGender !== null && $this->filterGender !== '', fn ($q) => $q->where('gender', $this->filterGender))
            ->get();

        $count = 0;
        foreach ($students as $student) {
            $this->upsertCard((int) $student->id, (int) $this->academicYearId, $batch);
            $count++;
        }

        $this->batchCode = $batch;
        notify()->success("{$count} carte(s) générée(s) — lot « {$batch} ».");
        $this->showForm = false;
        $this->editingId = null;
        $this->clearStudent();
        $this->resetErrorBag();
    }

    private function upsertCard(int $studentId, int $yearId, ?string $batch): void
    {
        $student = SchoolStudent::query()->findOrFail($studentId);

        $card = StudentIdCard::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
            ],
            [
                'batch_code' => $batch,
                'qr_token' => (string) Str::uuid(),
                'generated_at' => now(),
            ]
        );

        $card->batch_code = $batch;
        $card->generated_at = now();
        if (empty($card->qr_token)) {
            $card->qr_token = (string) Str::uuid();
            $card->qr_svg = null;
        }
        $card->barcode_data = $student->student_code.($batch ? "-{$batch}" : '');
        $card->save();

        if (empty($card->qr_svg)) {
            \School\Support\SchoolQrCode::ensureCached($card);
        }
    }

    public function render()
    {
        $years = AcademicYear::query()->orderByDesc('is_active')->orderByDesc('id')->get();
        $classes = SchoolClass::query()->where('is_active', true)->orderBy('name')->get();
        $sections = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION);
        $genders = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER);
        $statuses = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_ENROLLMENT_STATUS);
        $term = trim($this->search);

        if ($statuses->isNotEmpty() && $this->filterStatus !== '' && ! $statuses->contains('value', $this->filterStatus)) {
            $this->filterStatus = (string) $statuses->first()->value;
        }

        $cards = StudentIdCard::query()
            ->with(['student', 'academicYear'])
            ->when($this->filterYearId !== '', fn ($q) => $q->where('academic_year_id', (int) $this->filterYearId))
            ->when(trim($this->filterBatch) !== '', function ($q) {
                $like = '%'.mb_strtolower(trim($this->filterBatch)).'%';
                $q->whereRaw('LOWER(COALESCE(batch_code, \'\')) LIKE ?', [$like]);
            })
            ->when($this->listGender !== '', fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('gender', $this->listGender)))
            ->when($this->listClassId !== '' || $this->listSection !== '' || $this->listStatus !== '', function ($q) {
                $q->whereHas('student.enrollments', function ($eq) {
                    if ($this->filterYearId !== '') {
                        $eq->where('academic_year_id', (int) $this->filterYearId);
                    }
                    if ($this->listClassId !== '') {
                        $eq->where('class_id', (int) $this->listClassId);
                    }
                    if ($this->listSection !== '') {
                        $eq->where('section', $this->listSection);
                    }
                    if ($this->listStatus !== '') {
                        $eq->where('status', $this->listStatus);
                    }
                });
            })
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(COALESCE(barcode_data, \'\')) LIKE ?', [$like])
                        ->orWhereHas('student', function ($sq) use ($like) {
                            $sq->whereRaw('LOWER(student_code) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(12);

        return view('school::livewire.school.id-cards.index', [
            'years' => $years,
            'classes' => $classes,
            'sections' => $sections,
            'genders' => $genders,
            'statuses' => $statuses,
            'cards' => $cards,
            'studentResults' => $this->studentSearchResults(),
            'tenantCode' => $this->tenantCode(),
            'canManage' => $this->canSchool('school_id_cards.manage'),
        ])->layout('layouts.app', [
            'title' => 'École — Cartes ID',
            'subtitle' => 'Génération unitaire / lot + impression PDF.',
        ]);
    }
}
