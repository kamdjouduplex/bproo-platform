<?php

namespace Pressing\Http\Livewire\Agences;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InovCom\Users\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;

class AgencesIndex extends Component
{
    use AuthorizesPressingActions;
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public ?string $country = null;
    public ?string $city = null;
    public ?string $location = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?int $manager_user_id = null;
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePressingAction('agences.create');
        $this->resetForm();
        $this->code = 'AG-' . str_pad((string) (Agence::count() + 1), 3, '0', STR_PAD_LEFT);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePressingAction('agences.update');
        $agence = Agence::findOrFail($id);
        $this->editingId = $agence->id;
        $this->code = $agence->code;
        $this->name = $agence->name;
        $this->country = $agence->country;
        $this->city = $agence->city;
        $this->location = $agence->location;
        $this->phone = $agence->phone;
        $this->email = $agence->email;
        $this->manager_user_id = $agence->manager_user_id;
        $this->is_active = $agence->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizePressingAction($this->editingId ? 'agences.update' : 'agences.create');

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique(Agence::class, 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'manager_user_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            Agence::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Agence mise à jour.');
        } else {
            Agence::create($data);
            session()->flash('success', 'Agence créée.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizePressingAction('agences.delete');
        $agence = Agence::findOrFail($id);

        if ($agence->orders()->exists() || $agence->clients()->exists()) {
            session()->flash('error', 'Impossible de supprimer : des clients ou commandes y sont rattachés. Désactivez-la plutôt.');
            return;
        }

        $agence->delete();
        session()->flash('success', 'Agence supprimée.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->country = null;
        $this->city = null;
        $this->location = null;
        $this->phone = null;
        $this->email = null;
        $this->manager_user_id = null;
        $this->is_active = true;
    }

    public function render()
    {
        $this->authorizePressingAction('agences.view');

        $like = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $agences = Agence::query()
            ->with('manager')
            ->when($this->search !== '', function ($q) use ($like) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($like, $term) {
                    $inner->where('name', $like, $term)
                        ->orWhere('code', $like, $term)
                        ->orWhere('city', $like, $term);
                });
            })
            ->orderBy('name')
            ->paginate(10);

        return view('pressing::livewire.agences.index', [
            'agences' => $agences,
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canCreate' => $this->can('agences.create'),
            'canUpdate' => $this->can('agences.update'),
            'canDelete' => $this->can('agences.delete'),
        ])->layout('layouts.app', ['title' => 'Agences']);
    }
}
