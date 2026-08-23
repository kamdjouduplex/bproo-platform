<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ManagesTeacherProfileFields;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolTeacher;
use School\Support\SchoolOptionCatalog;
use School\Support\TeacherCodeGenerator;
use School\Support\TeacherPhotoStorage;

class SchoolTeachersIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ManagesTeacherProfileFields;
    use ResolvesTenantCode;
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $filterActive = '';

    /** @var mixed */
    public $photoFile = null;

    public bool $removePhoto = false;

    public function mount(): void
    {
        try {
            SchoolOptionCatalog::seedDefaults();
        } catch (\Throwable) {
        }

        if (! $this->canSchool('school_teachers.view') && ! $this->canSchool('school_teachers.manage')) {
            $own = SchoolTeacher::forUser(auth('tenant')->user());
            if ($own) {
                $this->redirect(route('tenant.school.teachers.show', [
                    'tenant' => $this->tenantCode(),
                    'id' => $own->id,
                ]), navigate: false);

                return;
            }
            abort(403, 'Permission refusée.');
        }
    }

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
        if (! $this->authorizeSchool('school_teachers.manage')) {
            return;
        }
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->openCreateForm();
    }

    protected function resetFormFields(): void
    {
        $this->resetTeacherProfileFields();
        $this->photoFile = null;
        $this->removePhoto = false;
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_teachers.manage')) {
            return;
        }

        if (trim($this->teacherCode) === '') {
            $this->teacherCode = TeacherCodeGenerator::next();
        }

        $locking = $this->lockOnSave;
        $this->validate(array_merge($this->teacherProfileRules($locking), [
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]), [
            'userId.required' => 'Choisissez l’utilisateur lié à cet enseignant.',
            'firstName.required' => 'Le prénom est obligatoire.',
            'lastName.required' => 'Le nom est obligatoire.',
            'phone.required' => 'Le téléphone est obligatoire.',
        ]);

        if ($locking && ! $this->photoFile) {
            $this->addError('photoFile', 'La photo est obligatoire pour valider le dossier.');

            return;
        }

        try {
            $photoPath = $this->photoFile ? TeacherPhotoStorage::store($this->photoFile) : null;
            $teacher = SchoolTeacher::query()->create($this->teacherProfilePayload(
                $photoPath,
                $locking,
                $locking ? auth('tenant')->id() : null
            ));
            $teacher->subjects()->sync(array_values(array_map('intval', $this->subjectIds)));
        } catch (\Throwable $e) {
            report($e);
            notify()->error('Impossible d’enregistrer l’enseignant. '.$e->getMessage());

            return;
        }

        notify()->success('Enseignant ajouté.');
        $this->cancel();
    }

    public function render()
    {
        $canManage = $this->canSchool('school_teachers.manage');
        $canView = $canManage || $this->canSchool('school_teachers.view');

        $term = trim($this->search);
        $teachers = SchoolTeacher::query()
            ->with('subjects')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(teacher_code, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12);

        return view('school::livewire.school.teachers.index', array_merge($this->teacherFormCatalogs(), [
            'teachers' => $teachers,
            'tenantCode' => $this->tenantCode(),
            'canManage' => $canManage,
            'canView' => $canView,
        ]))->layout('layouts.app', [
            'title' => 'École — Enseignants',
            'subtitle' => 'Dossiers enseignants liés aux utilisateurs.',
        ]);
    }
}
