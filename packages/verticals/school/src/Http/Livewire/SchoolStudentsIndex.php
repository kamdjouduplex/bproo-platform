<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
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
    use ResolvesTenantCode;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $filterGender = '';

    public string $filterActive = '';

    public string $studentCode = '';

    public string $firstName = '';

    public string $lastName = '';

    public ?string $gender = null;

    public ?string $birthDate = null;

    public ?string $parentFullName = null;

    public ?string $parentPhone = null;

    public ?string $parentEmail = null;

    public ?string $photoPath = null;

    /** @var mixed */
    public $photoFile = null;

    public bool $removePhoto = false;

    public ?string $croppedPhotoData = null;

    public ?string $notes = null;

    public bool $isActive = true;

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
        $this->studentCode = StudentCodeGenerator::next();
        $this->firstName = '';
        $this->lastName = '';
        $this->gender = null;
        $this->birthDate = null;
        $this->parentFullName = null;
        $this->parentPhone = null;
        $this->parentEmail = null;
        $this->photoPath = null;
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->croppedPhotoData = null;
        $this->notes = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (trim($this->studentCode) === '') {
            $this->studentCode = StudentCodeGenerator::next();
        }

        $genderValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER)->pluck('value')->all();

        $this->validate([
            'studentCode' => [
                'required',
                'string',
                'max:120',
                Rule::unique(SchoolStudent::class, 'student_code')->ignore($this->editingId),
            ],
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'max:40', Rule::in(array_merge([''], $genderValues))],
            'birthDate' => ['nullable', 'date'],
            'parentFullName' => ['nullable', 'string', 'max:255'],
            'parentPhone' => ['nullable', 'string', 'max:80'],
            'parentEmail' => ['nullable', 'email', 'max:255'],
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'notes' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        $photoPath = null;
        if (filled($this->croppedPhotoData)) {
            $photoPath = StudentPhotoStorage::storeFromDataUri($this->croppedPhotoData);
        } elseif ($this->photoFile) {
            $photoPath = StudentPhotoStorage::store($this->photoFile);
        }

        SchoolStudent::query()->create([
            'student_code' => $this->studentCode,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'gender' => $this->gender !== '' && $this->gender !== null ? $this->gender : null,
            'birth_date' => $this->birthDate,
            'parent_full_name' => $this->parentFullName !== '' && $this->parentFullName !== null ? $this->parentFullName : null,
            'parent_phone' => $this->parentPhone !== '' && $this->parentPhone !== null ? $this->parentPhone : null,
            'parent_email' => $this->parentEmail !== '' && $this->parentEmail !== null ? $this->parentEmail : null,
            'photo_path' => $photoPath,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Élève ajouté.');

        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $genders = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER);

        $students = SchoolStudent::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_full_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(parent_email, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterGender !== '', fn ($q) => $q->where('gender', $this->filterGender))
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->paginate(12);

        return view('school::livewire.school.students.index', [
            'students' => $students,
            'genders' => $genders,
            'genderLabels' => $genders->pluck('label', 'value'),
            'tenantCode' => $this->tenantCode(),
            'canManage' => $this->canSchool('school_students.manage'),
            'croppedPreview' => $this->croppedPhotoData,
        ])->layout('layouts.app', [
            'title' => 'École — Élèves',
            'subtitle' => 'Profils permanents + ID auto (SCH-AAAA-####).',
        ]);
    }
}
