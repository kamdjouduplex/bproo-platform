<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolOption;
use School\Support\SchoolOptionCatalog;

class SchoolOptionsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $activeGroup = SchoolOptionCatalog::GROUP_SECTION;

    public string $search = '';

    public string $filterActive = '';

    public string $value = '';

    public string $label = '';

    public int $sortOrder = 100;

    public bool $isActive = true;

    public function mount(): void
    {
        $group = (string) request()->query('group', '');
        if (array_key_exists($group, SchoolOptionCatalog::groups())) {
            $this->activeGroup = $group;
        }
    }

    public function setGroup(string $group): void
    {
        if (! array_key_exists($group, SchoolOptionCatalog::groups())) {
            return;
        }

        $this->activeGroup = $group;
        $this->resetPage();
        $this->cancel();
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
        $this->openCreateForm();
    }

    protected function resetFormFields(): void
    {
        $this->value = '';
        $this->label = '';
        $this->sortOrder = 100;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'activeGroup' => ['required', Rule::in(array_keys(SchoolOptionCatalog::groups()))],
            'value' => [
                'required',
                'string',
                'max:120',
                Rule::unique(SchoolOption::class, 'value')
                    ->where('group_key', $this->activeGroup)
                    ->ignore($this->editingId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
            'isActive' => ['boolean'],
        ]);

        $payload = [
            'group_key' => $this->activeGroup,
            'value' => trim($this->value),
            'label' => trim($this->label),
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        SchoolOption::query()->create($payload);
        notify()->success('Option ajoutée.');

        $this->cancel();
    }

    public function seedDefaults(): void
    {
        SchoolOptionCatalog::seedDefaults();
        notify()->success('Valeurs par défaut ajoutées (sans écraser l’existant).');
    }

    public function render()
    {
        $groups = SchoolOptionCatalog::groups();
        $term = trim($this->search);

        $options = SchoolOption::query()
            ->where('group_key', $this->activeGroup)
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(value) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(label) LIKE ?', [$like]);
                });
            })
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->paginate(20);

        return view('school::livewire.school.options.index', [
            'groups' => $groups,
            'options' => $options,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Listes configurables',
            'subtitle' => 'Sections, genres, statuts — créés par l’admin.',
        ]);
    }
}
