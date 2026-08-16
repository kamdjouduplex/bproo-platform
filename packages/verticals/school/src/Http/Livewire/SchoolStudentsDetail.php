<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolExamMark;
use School\Models\SchoolOption;
use School\Models\SchoolStudent;
use School\Models\SchoolStudentLedgerEntry;
use School\Support\SchoolOptionCatalog;
use School\Support\StudentLedgerService;
use School\Support\StudentPhotoStorage;
use School\Support\StudentProfileCompletion;

class SchoolStudentsDetail extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithFileUploads;

    public int $studentId;

    public string $mode = 'show';

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

    /** Dedicated quick upload on manage page (independent from edit modal). */
    /** @var mixed */
    public $profilePhotoFile = null;

    /** Cropped data URI waiting to be saved with create/edit form. */
    public ?string $croppedPhotoData = null;

    public ?string $notes = null;

    public bool $isActive = true;

    public function mount(int $id): void
    {
        $this->studentId = $id;
        SchoolStudent::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
        if ($this->mode === 'manage' && ! $this->canSchool('school_students.manage')) {
            $this->mode = 'show';
        }
    }

    public function saveCroppedProfilePhoto(string $dataUrl): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (! str_starts_with($dataUrl, 'data:image/') || strlen($dataUrl) > 6_000_000) {
            notify()->error('Image invalide ou trop volumineuse.');

            return;
        }

        try {
            $student = $this->entity();
            $path = StudentPhotoStorage::storeFromDataUri($dataUrl, $student);
            $student->update(['photo_path' => $path]);
            $this->profilePhotoFile = null;
            $this->croppedPhotoData = null;
            notify()->success('Photo de profil cadrée et enregistrée.');
        } catch (\Throwable $e) {
            notify()->error($e->getMessage());
        }
    }

    public function setCroppedPhoto(string $dataUrl): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        if (! str_starts_with($dataUrl, 'data:image/')) {
            notify()->error('Image invalide.');

            return;
        }

        if (strlen($dataUrl) > 6_000_000) {
            notify()->error('Photo trop volumineuse après cadrage.');

            return;
        }

        $this->croppedPhotoData = $dataUrl;
        $this->photoFile = null;
        $this->removePhoto = false;
        notify()->success('Cadrage prêt — enregistrez le formulaire pour confirmer.');
    }

    public function clearProfilePhoto(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        $student = $this->entity();
        StudentPhotoStorage::delete($student->photo_path);
        $student->update(['photo_path' => null]);
        $this->profilePhotoFile = null;
        $this->croppedPhotoData = null;
        notify()->success('Photo de profil retirée.');
    }

    public function edit(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $student = $this->entity();
        foreach (['studentCode' => 'student_code', 'firstName' => 'first_name', 'lastName' => 'last_name',
            'gender' => 'gender', 'parentFullName' => 'parent_full_name', 'parentPhone' => 'parent_phone',
            'parentEmail' => 'parent_email', 'photoPath' => 'photo_path', 'notes' => 'notes'] as $property => $column) {
            $this->{$property} = $student->{$column};
        }
        $this->birthDate = $student->birth_date?->format('Y-m-d');
        $this->isActive = (bool) $student->is_active;
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->openEditForm($student->id);
    }

    public function activate(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $this->entity()->update(['is_active' => true]);
        notify()->success('Élève activé.');
    }

    public function deactivate(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }
        $this->entity()->update(['is_active' => false]);
        notify()->success('Élève désactivé.');
    }

    protected function resetFormFields(): void
    {
        $this->studentCode = $this->firstName = $this->lastName = '';
        $this->gender = $this->birthDate = $this->parentFullName = $this->parentPhone = null;
        $this->parentEmail = $this->photoPath = $this->notes = null;
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->croppedPhotoData = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_students.manage')) {
            return;
        }

        $genderValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER)->pluck('value')->all();
        $this->validate([
            'studentCode' => ['required', 'string', 'max:120', Rule::unique(SchoolStudent::class, 'student_code')->ignore($this->studentId)],
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

        $student = $this->entity();
        $photoPath = $student->photo_path;
        if ($this->removePhoto) {
            StudentPhotoStorage::delete($photoPath);
            $photoPath = null;
            $this->croppedPhotoData = null;
        }
        if (filled($this->croppedPhotoData)) {
            $photoPath = StudentPhotoStorage::storeFromDataUri($this->croppedPhotoData, $student);
            $this->croppedPhotoData = null;
        } elseif ($this->photoFile) {
            $photoPath = StudentPhotoStorage::store($this->photoFile, $student);
        }

        $student->update([
            'student_code' => $this->studentCode,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'gender' => filled($this->gender) ? $this->gender : null,
            'birth_date' => $this->birthDate,
            'parent_full_name' => filled($this->parentFullName) ? $this->parentFullName : null,
            'parent_phone' => filled($this->parentPhone) ? $this->parentPhone : null,
            'parent_email' => filled($this->parentEmail) ? $this->parentEmail : null,
            'photo_path' => $photoPath,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Élève mis à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolStudent
    {
        return SchoolStudent::query()->findOrFail($this->studentId);
    }

    public function render()
    {
        $student = $this->entity();
        $isManage = $this->mode === 'manage';
        $canManage = $this->canSchool('school_students.manage');
        $genders = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_GENDER);
        $genderLabel = $genders->firstWhere('value', $student->gender)?->label ?? $student->gender;
        $completion = StudentProfileCompletion::for($student);
        $enrollments = $student->enrollments()->with(['academicYear', 'schoolClass'])->orderByDesc('id')->get();
        $payments = $student->payments()->with('academicYear')->orderByDesc('id')->limit(50)->get();
        $ledger = SchoolStudentLedgerEntry::query()
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();
        $balance = app(StudentLedgerService::class)->balance((int) $student->id);
        $examMarks = SchoolExamMark::query()
            ->with(['exam.subject'])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();
        $tenantCode = $this->tenantCode();
        $photoUrl = $student->photoUrl($tenantCode);

        return view('school::livewire.school.students.detail', compact(
            'student', 'isManage', 'canManage', 'genders', 'genderLabel', 'completion',
            'enrollments', 'payments', 'examMarks', 'ledger', 'balance', 'photoUrl'
        ) + [
            'tenantCode' => $tenantCode,
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$student->full_name,
            'subtitle' => $isManage ? 'Actions et modification du profil.' : 'Profil + historique permanent.',
        ]);
    }
}
