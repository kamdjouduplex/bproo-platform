<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolClass;
use School\Models\SchoolOption;
use School\Support\SchoolOptionCatalog;

class SchoolClassesIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $filterSection = '';
    public string $filterActive = '';
    public string $name = '';
    public ?string $section = null;
    public bool $isActive = true;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterSection(): void { $this->resetPage(); }
    public function updatedFilterActive(): void { $this->resetPage(); }

    public function create(): void { $this->openCreateForm(); }

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

        SchoolClass::query()->create([
            'name' => $this->name,
            'section' => $this->section !== '' && $this->section !== null ? $this->section : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Classe ajoutée.');
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $sections = SchoolOption::forGroup(SchoolOptionCatalog::GROUP_SECTION);
        $classes = SchoolClass::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(section, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterSection !== '', fn ($q) => $q->where('section', $this->filterSection))
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')->paginate(12);

        return view('school::livewire.school.classes.index', [
            'classes' => $classes,
            'sections' => $sections,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Classes',
            'subtitle' => 'Gérer les classes.',
        ]);
    }
}
