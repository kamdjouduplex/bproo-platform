<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ManagesTeacherProfileFields;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolOption;
use School\Models\SchoolTeacher;
use School\Support\SchoolOptionCatalog;
use School\Support\TeacherPhotoStorage;

class SchoolTeachersDetail extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ManagesTeacherProfileFields;
    use ResolvesTenantCode;
    use WithFileUploads;

    public int $teacherId;

    public string $mode = 'show';

    /** @var mixed */
    public $photoFile = null;

    public bool $removePhoto = false;

    public function mount(int $id): void
    {
        $this->teacherId = $id;
        $teacher = SchoolTeacher::query()->findOrFail($id);
        $user = auth('tenant')->user();
        $isOwn = $teacher->isOwnedBy($user);

        if (! $isOwn && ! $this->canSchool('school_teachers.view') && ! $this->canSchool('school_teachers.manage')) {
            abort(403, 'Permission refusée.');
        }

        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
        if ($this->mode === 'manage' && ! $this->canSchool('school_teachers.manage') && ! ($isOwn && ! $teacher->isValidated())) {
            $this->mode = 'show';
        }
    }

    public function edit(): void
    {
        $teacher = $this->entity();
        if (! $this->canEdit($teacher)) {
            notify()->error($teacher->isValidated()
                ? 'Dossier validé. Contactez l’administration pour une correction.'
                : 'Permission refusée pour cette action.');

            return;
        }
        $this->photoFile = null;
        $this->removePhoto = false;
        $this->fillTeacherProfileFields($teacher);
        $this->openEditForm($teacher->id);
    }

    public function activate(): void
    {
        if (! $this->authorizeSchool('school_teachers.manage')) {
            return;
        }
        $this->entity()->update(['is_active' => true]);
        notify()->success('Dossier enseignant réactivé.');
    }

    public function deactivate(): void
    {
        if (! $this->authorizeSchool('school_teachers.manage')) {
            return;
        }
        $this->entity()->update(['is_active' => false]);
        notify()->success('Dossier enseignant désactivé.');
    }

    protected function resetFormFields(): void
    {
        $this->resetTeacherProfileFields();
        $this->photoFile = null;
        $this->removePhoto = false;
    }

    public function save(): void
    {
        $teacher = $this->entity();
        if (! $this->canEdit($teacher)) {
            notify()->error($teacher->isValidated()
                ? 'Dossier validé. Contactez l’administration pour une correction.'
                : 'Permission refusée pour cette action.');

            return;
        }

        $isAdmin = $this->canSchool('school_teachers.manage');
        $locking = $isAdmin ? $this->lockOnSave : true;

        $this->validate(array_merge($this->teacherProfileRules($locking, $teacher->id), [
            'photoFile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]));

        $existingPhoto = $this->removePhoto ? null : $teacher->photo_path;
        if ($locking && ! $this->photoFile && ! $existingPhoto) {
            $this->addError('photoFile', 'La photo est obligatoire pour valider le dossier.');

            return;
        }

        $photoPath = $teacher->photo_path;
        if ($this->removePhoto && ! $this->photoFile) {
            TeacherPhotoStorage::delete($teacher->photo_path);
            $photoPath = '';
        }
        if ($this->photoFile) {
            $photoPath = TeacherPhotoStorage::store($this->photoFile, $teacher);
        }

        $teacher->update($this->teacherProfilePayload(
            $photoPath,
            $locking,
            $locking ? auth('tenant')->id() : null
        ));
        $teacher->subjects()->sync(array_map('intval', $this->subjectIds));

        notify()->success($locking
            ? 'Dossier validé. Il ne peut plus être modifié par l’enseignant.'
            : 'Enseignant mis à jour.');
        $this->cancel();
    }

    protected function canEdit(SchoolTeacher $teacher): bool
    {
        if ($this->canSchool('school_teachers.manage')) {
            return true;
        }

        return $teacher->isOwnedBy(auth('tenant')->user()) && ! $teacher->isValidated();
    }

    protected function entity(): SchoolTeacher
    {
        return SchoolTeacher::query()->with(['subjects', 'user'])->findOrFail($this->teacherId);
    }

    public function render()
    {
        $teacher = $this->entity();
        $user = auth('tenant')->user();
        $isOwn = $teacher->isOwnedBy($user);
        $isManage = $this->mode === 'manage' && $this->canSchool('school_teachers.manage');
        $canEdit = $this->canEdit($teacher);
        $optionLabel = function (?string $group, ?string $value): string {
            if (! $value) {
                return '—';
            }
            $row = SchoolOption::forGroup($group, false)->firstWhere('value', $value);

            return $row?->label ?? $value;
        };

        return view('school::livewire.school.teachers.detail', array_merge($this->teacherFormCatalogs($teacher->user_id ? (int) $teacher->user_id : null), [
            'teacher' => $teacher,
            'isManage' => $isManage,
            'isOwn' => $isOwn,
            'canEdit' => $canEdit,
            'canViewList' => $this->canSchool('school_teachers.view') || $this->canSchool('school_teachers.manage'),
            'tenantCode' => $this->tenantCode(),
            'photoUrl' => $teacher->photoUrl($this->tenantCode()),
            'genderLabel' => $optionLabel(SchoolOptionCatalog::GROUP_GENDER, $teacher->gender),
            'levelLabel' => $optionLabel(SchoolOptionCatalog::GROUP_EDUCATION_LEVEL, $teacher->education_level),
            'diplomaLabel' => $optionLabel(SchoolOptionCatalog::GROUP_DIPLOMA_KIND, $teacher->diploma_kind),
            'sectionLabel' => $optionLabel(SchoolOptionCatalog::GROUP_TEACHING_SECTION, $teacher->teaching_section),
        ]))->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$teacher->full_name,
            'subtitle' => $isManage ? 'Dossier enseignant (ADM).' : 'Dossier enseignant.',
        ]);
    }
}
