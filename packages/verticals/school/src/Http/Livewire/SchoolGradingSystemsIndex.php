<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolGradingSystem;

class SchoolGradingSystemsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $filterActive = '';
    public string $code = '';
    public string $name = '';
    public float $scaleBase = 20;
    public ?string $description = null;
    public bool $isActive = true;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterActive(): void { $this->resetPage(); }
    public function create(): void { $this->openCreateForm(); $this->scaleBase = 20; $this->isActive = true; }

    protected function resetFormFields(): void
    {
        $this->code = '';
        $this->name = '';
        $this->scaleBase = 20;
        $this->description = null;
        $this->isActive = true;
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'scaleBase' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        SchoolGradingSystem::query()->create([
            'code' => filled($this->code) ? $this->code : null,
            'name' => $this->name,
            'scale_base' => $this->scaleBase,
            'description' => filled($this->description) ? $this->description : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Système de notation créé.');
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $systems = SchoolGradingSystem::query()
            ->withCount('scales')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')->paginate(12);

        return view('school::livewire.school.grading.systems-index', [
            'systems' => $systems,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Notation',
            'subtitle' => 'Systèmes de notation et barèmes configurables.',
        ]);
    }
}
