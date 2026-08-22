<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ManagesStudentProfileFields;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Support\SchoolOptionCatalog;
use School\Support\StudentCodeGenerator;
use School\Support\StudentPhotoStorage;

class SchoolStudentsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ManagesStudentProfileFields;
    use ResolvesTenantCode;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $filterGender = '';

    public string $filterActive = '';

    /** @var mixed */
    public $photoFile = null;

    public bool $removePhoto = false;

    public ?string $croppedPhotoData = null;

    public function mount(): void
    {
        try {
            \School\Support\SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
            // Options table may not be ready yet.
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterGender(): void
    {
        $this->resetPage();
    }

    public function updatedFilterActive(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $this->openCreateForm();
        $this->studentCode = StudentCodeGenerator::next();
    }

    public function setCroppedPhoto(string $dataUrl): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (! str_starts_with($dataUrl, 'data:image/') || strlen($dataUrl) > 6_000_000) {
            notify()->error('Image invalide ou trop volumineuse.');

            return;
        }

        $this->croppedPhotoData = $dataUrl;
        $this->photoFile = null;
        $this->removePhoto = false;
        notify()->success('Photo cadrée — cliquez Enregistrer pour créer l’élève.');
    }

    protected function resetFormFields(): void
    {
        $this->resetStudentProfileFields();
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->croppedPhotoData = null;
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (trim($this->studentCode) === '') {
            $this->studentCode = StudentCodeGenerator::next();
        }

        $this->validate(array_merge($this->studentProfileRules(), [
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]));

        $photoPath = null;
        if (filled($this->croppedPhotoData)) {
            $photoPath = StudentPhotoStorage::storeFromDataUri($this->croppedPhotoData);
        } elseif ($this->photoFile) {
            $photoPath = StudentPhotoStorage::store($this->photoFile);
        }

        SchoolStudent::query()->create($this->studentProfilePayload($photoPath));
        notify()->success('Élève ajouté.');

        $this->cancel();
    }

    public function exportExcel()
    {
        if (! $this->canSchool('school_students.view') && ! $this->canSchool('school_students.manage')) {
            abort(403, 'Permission refusée.');
        }

        $students = $this->studentsQuery()
            ->with(['currentEnrollment.schoolClass'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return \School\Support\SchoolMinistryExport::download($students);
    }

    protected function studentsQuery()
    {
        $term = trim($this->search);

        return SchoolStudent::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(nisu, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_full_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_email, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterGender !== '', fn ($q) => $q->where('gender', $this->filterGender))
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false));
    }

    public function render()
    {
        $genders = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER);
        $relationships = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_PARENT_RELATIONSHIP);

        $students = $this->studentsQuery()
            ->with(['currentEnrollment.schoolClass'])
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->paginate(12);

        return view('school::livewire.school.students.index', [
            'students' => $students,
            'genders' => $genders,
            'relationships' => $relationships,
            'genderLabels' => $genders->pluck('label', 'value'),
            'tenantCode' => $this->tenantCode(),
            'canManage' => $this->canSchool('school_students.manage'),
            'croppedPreview' => $this->croppedPhotoData,
        ])->layout('layouts.app', [
            'title' => 'École — Élèves',
            'subtitle' => 'Profils permanents — l’inscription se fait chaque année.',
        ]);
    }
}
