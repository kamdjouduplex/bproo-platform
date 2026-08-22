<?php

namespace School\Http\Livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use School\Http\Livewire\Concerns\AuthorizesSchoolActions;
use School\Http\Livewire\Concerns\ManagesSchoolCrudUi;
use School\Http\Livewire\Concerns\ResolvesTenantCode;
use School\Models\SchoolRoom;

class SchoolRoomsIndex extends Component
{
    use AuthorizesSchoolActions;
    use ManagesSchoolCrudUi;
    use ResolvesTenantCode;
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public ?string $capacity = null;

    public ?string $notes = null;

    public bool $isActive = true;

    public function mount(): void
    {
        if (! $this->canSchool('school_timetable.view')) {
            abort(403, 'Permission refusée.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }
        $this->openCreateForm();
    }

    public function edit(int $id): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }
        $room = SchoolRoom::query()->findOrFail($id);
        $this->name = $room->name;
        $this->capacity = $room->capacity !== null ? (string) $room->capacity : null;
        $this->notes = $room->notes;
        $this->isActive = (bool) $room->is_active;
        $this->openEditForm($id);
    }

    public function save(): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $query = SchoolRoom::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($this->name))]);
        if ($this->editingId) {
            $query->where('id', '!=', $this->editingId);
        }
        if ($query->exists()) {
            notify()->error('Cette salle existe déjà.');

            return;
        }

        $payload = [
            'name' => trim($this->name),
            'capacity' => filled($this->capacity) ? (int) $this->capacity : null,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            SchoolRoom::query()->findOrFail($this->editingId)->update($payload);
            notify()->success('Salle mise à jour.');
        } else {
            SchoolRoom::query()->create($payload);
            notify()->success('Salle créée. Vous pouvez l’utiliser dans les cours et l’emploi du temps.');
        }

        $this->cancel();
    }

    public function delete(int $id): void
    {
        if (! $this->authorizeSchool('school_timetable.manage')) {
            return;
        }

        SchoolRoom::query()->where('id', $id)->delete();
        notify()->success('Salle retirée.');
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->capacity = null;
        $this->notes = null;
        $this->isActive = true;
    }

    public function render()
    {
        $term = trim($this->search);
        $rooms = SchoolRoom::tableReady()
            ? SchoolRoom::query()
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.mb_strtolower($term).'%';
                    $q->where(function ($inner) use ($like) {
                        $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(notes, \'\')) LIKE ?', [$like]);
                    });
                })
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20)
            : new LengthAwarePaginator([], 0, 20);

        return view('school::livewire.school.rooms.index', [
            'rooms' => $rooms,
            'canManage' => $this->canSchool('school_timetable.manage'),
            'tenantCode' => $this->tenantCode(),
        ])->layout('layouts.app', [
            'title' => 'École — Salles',
            'subtitle' => 'Locaux physiques (Salle 3, Labo, Info…) — distincts des classes d’élèves.',
        ]);
    }
}
