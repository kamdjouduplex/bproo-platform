<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolTeacher;

class SchoolTeachersDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $teacherId;
    public string $mode = 'show';
    public string $fullName = '';
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $address = null;
    public bool $isActive = true;

    public function mount(int $id): void
    {
        $this->teacherId = $id;
        SchoolTeacher::query()->findOrFail($id);
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $teacher = $this->entity();
        $this->fullName = $teacher->full_name;
        $this->phone = $teacher->phone;
        $this->email = $teacher->email;
        $this->address = $teacher->address;
        $this->isActive = (bool) $teacher->is_active;
        $this->openEditForm($teacher->id);
    }

    public function activate(): void { $this->entity()->update(['is_active' => true]); notify()->success('Enseignant activé.'); }
    public function deactivate(): void { $this->entity()->update(['is_active' => false]); notify()->success('Enseignant désactivé.'); }

    protected function resetFormFields(): void
    {
        $this->fullName = '';
        $this->phone = $this->email = $this->address = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);
        $this->entity()->update([
            'full_name' => $this->fullName,
            'phone' => filled($this->phone) ? $this->phone : null,
            'email' => filled($this->email) ? $this->email : null,
            'address' => filled($this->address) ? $this->address : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Enseignant mis à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolTeacher
    {
        return SchoolTeacher::query()->findOrFail($this->teacherId);
    }

    public function render()
    {
        $teacher = $this->entity();
        $isManage = $this->mode === 'manage';
        return view('school::livewire.school.teachers.detail', compact('teacher', 'isManage') + [
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$teacher->full_name,
            'subtitle' => $isManage ? 'Actions et modification de l’enseignant.' : 'Détail de l’enseignant.',
        ]);
    }
}
