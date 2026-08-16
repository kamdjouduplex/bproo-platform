<?php

namespace School\Http\Livewire;

use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolSubject;

class SchoolSubjectsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $subjectId;
    public string $mode = 'show';
    public string $name = '';
    public ?string $code = null;
    public bool $isActive = true;

    public function mount(int $id): void
    {
        $this->subjectId = $id;
        SchoolSubject::query()->findOrFail($id);
        $route = request()->route()?->getName() ?? '';
        $this->mode = str_ends_with($route, '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $s = $this->entity();
        $this->name = $s->name;
        $this->code = $s->code;
        $this->isActive = (bool) $s->is_active;
        $this->openEditForm($s->id);
    }

    public function activate(): void { $this->entity()->update(['is_active' => true]); notify()->success('Matière activée.'); }
    public function deactivate(): void { $this->entity()->update(['is_active' => false]); notify()->success('Matière désactivée.'); }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->code = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:80'],
            'isActive' => ['boolean'],
        ]);
        $this->entity()->update([
            'name' => $this->name,
            'code' => $this->code !== '' && $this->code !== null ? $this->code : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Matière mise à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolSubject
    {
        return SchoolSubject::query()->findOrFail($this->subjectId);
    }

    public function render()
    {
        $subject = $this->entity();
        $isManage = $this->mode === 'manage';

        return view('school::livewire.school.subjects.detail', [
            'subject' => $subject,
            'isManage' => $isManage,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$subject->name,
            'subtitle' => $isManage ? 'Actions et modification de la matière.' : 'Détail de la matière.',
        ]);
    }
}
