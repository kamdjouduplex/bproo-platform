<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolClass;
use School\Models\SchoolOption;
use School\Support\SchoolOptionCatalog;

class SchoolClassesDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $classId;
    public string $mode = 'show';
    public string $name = '';
    public ?string $section = null;
    public bool $isActive = true;

    public function mount(int $id): void
    {
        $this->classId = $id;
        SchoolClass::query()->findOrFail($id);
        $route = request()->route()?->getName() ?? '';
        $this->mode = str_ends_with($route, '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $class = $this->entity();
        $this->name = $class->name;
        $this->section = $class->section;
        $this->isActive = (bool) $class->is_active;
        $this->openEditForm($class->id);
    }

    public function activate(): void
    {
        $this->entity()->update(['is_active' => true]);
        notify()->success('Classe activée.');
    }

    public function deactivate(): void
    {
        $this->entity()->update(['is_active' => false]);
        notify()->success('Classe désactivée.');
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->section = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        $sectionValues = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION)->pluck('value')->all();
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:120', Rule::in(array_merge([''], $sectionValues))],
            'isActive' => ['boolean'],
        ]);
        $this->entity()->update([
            'name' => $this->name,
            'section' => $this->section !== '' && $this->section !== null ? $this->section : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Classe mise à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolClass
    {
        return SchoolClass::query()->findOrFail($this->classId);
    }

    public function render()
    {
        $class = $this->entity();
        $isManage = $this->mode === 'manage';
        $sections = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION);
        $sectionLabel = $sections->firstWhere('value', $class->section)?->label ?? $class->section;

        return view('school::livewire.school.classes.detail', [
            'class' => $class,
            'isManage' => $isManage,
            'sections' => $sections,
            'sectionLabel' => $sectionLabel,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$class->name,
            'subtitle' => $isManage ? 'Actions et modification de la classe.' : 'Détail de la classe.',
        ]);
    }
}
