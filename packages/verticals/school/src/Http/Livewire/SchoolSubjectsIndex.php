<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolSubject;

class SchoolSubjectsIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';
    public string $filterActive = '';
    public string $name = '';
    public ?string $code = null;
    public bool $isActive = true;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterActive(): void { $this->resetPage(); }
    public function create(): void { $this->openCreateForm(); }

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
        SchoolSubject::query()->create([
            'name' => $this->name,
            'code' => $this->code !== '' && $this->code !== null ? $this->code : null,
            'is_active' => $this->isActive,
        ]);
        notify()->success('Matière ajoutée.');
        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);
        $subjects = SchoolSubject::query()
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

        return view('school::livewire.school.subjects.index', [
            'subjects' => $subjects,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Matières',
            'subtitle' => 'Gérer les matières.',
        ]);
    }
}
