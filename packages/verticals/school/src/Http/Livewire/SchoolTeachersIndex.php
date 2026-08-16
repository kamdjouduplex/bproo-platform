<?php

namespace School\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolTeacher;

class SchoolTeachersIndex extends Component
{
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';

    public string $filterActive = '';

    public string $fullName = '';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    public bool $isActive = true;

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
        $this->fullName = '';
        $this->phone = null;
        $this->email = null;
        $this->address = null;
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

        $payload = [
            'full_name' => $this->fullName,
            'phone' => $this->phone !== '' && $this->phone !== null ? $this->phone : null,
            'email' => $this->email !== '' && $this->email !== null ? $this->email : null,
            'address' => $this->address !== '' && $this->address !== null ? $this->address : null,
            'is_active' => $this->isActive,
        ];

        SchoolTeacher::query()->create($payload);
        notify()->success('Enseignant ajouté.');

        $this->cancel();
    }

    public function render()
    {
        $term = trim($this->search);

        $teachers = SchoolTeacher::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
                });
            })
            ->when($this->filterActive === '1', fn ($q) => $q->where('is_active', true))
            ->when($this->filterActive === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('full_name')
            ->paginate(12);

        return view('school::livewire.school.teachers.index', [
            'teachers' => $teachers,
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Enseignants',
            'subtitle' => 'Gérer les enseignants.',
        ]);
    }
}
