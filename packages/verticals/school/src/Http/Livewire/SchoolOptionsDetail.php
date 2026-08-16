<?php

namespace School\Http\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolOption;
use School\Support\SchoolOptionCatalog;

class SchoolOptionsDetail extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;

    public int $optionId;
    public string $mode = 'show';
    public string $activeGroup = '';
    public string $value = '';
    public string $label = '';
    public int $sortOrder = 100;
    public bool $isActive = true;

    public function mount(int $id): void
    {
        $option = SchoolOption::query()->findOrFail($id);
        $this->optionId = $id;
        $this->activeGroup = $option->group_key;
        $this->mode = str_ends_with(request()->route()?->getName() ?? '', '.manage') ? 'manage' : 'show';
    }

    public function edit(): void
    {
        $option = $this->entity();
        $this->activeGroup = $option->group_key;
        $this->value = $option->value;
        $this->label = $option->label;
        $this->sortOrder = (int) $option->sort_order;
        $this->isActive = (bool) $option->is_active;
        $this->openEditForm($option->id);
    }

    public function toggleActive(): void
    {
        $option = $this->entity();
        $option->update(['is_active' => ! $option->is_active]);
        notify()->success($option->is_active ? 'Option activée.' : 'Option désactivée.');
    }

    protected function resetFormFields(): void
    {
        $this->value = $this->label = '';
        $this->sortOrder = 100;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'activeGroup' => ['required', Rule::in(array_keys(SchoolOptionCatalog::groups()))],
            'value' => ['required', 'string', 'max:120',
                Rule::unique(SchoolOption::class, 'value')->where('group_key', $this->activeGroup)->ignore($this->optionId)],
            'label' => ['required', 'string', 'max:255'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
            'isActive' => ['boolean'],
        ]);
        $this->entity()->update([
            'group_key' => $this->activeGroup, 'value' => trim($this->value),
            'label' => trim($this->label), 'sort_order' => $this->sortOrder, 'is_active' => $this->isActive,
        ]);
        notify()->success('Option mise à jour.');
        $this->cancel();
    }

    protected function entity(): SchoolOption { return SchoolOption::query()->findOrFail($this->optionId); }

    public function render()
    {
        $option = $this->entity();
        $isManage = $this->mode === 'manage';
        $groups = SchoolOptionCatalog::groups();
        $groupLabel = $groups[$option->group_key]['label'] ?? $option->group_key;
        return view('school::livewire.school.options.detail', compact('option', 'isManage', 'groups', 'groupLabel') + [
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => ($isManage ? 'Gérer — ' : 'Voir — ').$option->label,
            'subtitle' => $isManage ? 'Actions et modification de l’option.' : 'Détail de l’option.',
        ]);
    }
}
