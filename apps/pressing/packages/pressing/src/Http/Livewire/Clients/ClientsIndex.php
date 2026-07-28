<?php

namespace Pressing\Http\Livewire\Clients;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\Agence;
use Pressing\Models\PressingClient;

class ClientsIndex extends Component
{
    use AuthorizesPressingActions;
    use WithPagination;

    public string $search = '';
    public ?int $agenceFilter = null;
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $code = '';
    public ?int $agence_id = null;
    public string $last_name = '';
    public string $first_name = '';
    public string $whatsapp = '';
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $address = null;
    public ?string $notes = null;
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizePressingAction('pressing_clients.create');
        $this->resetForm();
        $this->code = 'CL-' . str_pad((string) (PressingClient::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);
        $this->agence_id = Agence::where('is_active', true)->value('id');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePressingAction('pressing_clients.update');
        $client = PressingClient::findOrFail($id);
        $this->editingId = $client->id;
        $this->code = $client->code;
        $this->agence_id = $client->agence_id;
        $this->last_name = $client->last_name;
        $this->first_name = $client->first_name;
        $this->whatsapp = $client->whatsapp;
        $this->phone = $client->phone;
        $this->email = $client->email;
        $this->address = $client->address;
        $this->notes = $client->notes;
        $this->is_active = $client->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizePressingAction($this->editingId ? 'pressing_clients.update' : 'pressing_clients.create');

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique(PressingClient::class, 'code')->ignore($this->editingId)],
            'agence_id' => ['required', 'integer', 'exists:tenant.agences,id'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'whatsapp' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            PressingClient::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Client mis à jour.');
        } else {
            PressingClient::create($data);
            session()->flash('success', 'Client créé.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizePressingAction('pressing_clients.delete');
        $client = PressingClient::findOrFail($id);

        if ($client->orders()->exists()) {
            session()->flash('error', 'Impossible de supprimer : ce client a des commandes. Désactivez-le plutôt.');
            return;
        }

        $client->delete();
        session()->flash('success', 'Client supprimé.');
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
        $this->agence_id = null;
        $this->last_name = '';
        $this->first_name = '';
        $this->whatsapp = '';
        $this->phone = null;
        $this->email = null;
        $this->address = null;
        $this->notes = null;
        $this->is_active = true;
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_clients.view');

        $like = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $clients = PressingClient::query()
            ->with('agence')
            ->withCount(['orders', 'orders as open_orders_count' => fn ($q) => $q->whereIn('status', ['open', 'ready'])])
            ->withSum('orders as total_revenue', 'total')
            ->when($this->agenceFilter, fn ($q) => $q->where('agence_id', $this->agenceFilter))
            ->when($this->search !== '', function ($q) use ($like) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($like, $term) {
                    $inner->where('last_name', $like, $term)
                        ->orWhere('first_name', $like, $term)
                        ->orWhere('whatsapp', $like, $term)
                        ->orWhere('phone', $like, $term)
                        ->orWhere('code', $like, $term);
                });
            })
            ->orderBy('last_name')
            ->paginate(12);

        return view('pressing::livewire.clients.index', [
            'clients' => $clients,
            'agences' => Agence::where('is_active', true)->orderBy('name')->get(),
            'canCreate' => $this->can('pressing_clients.create'),
            'canUpdate' => $this->can('pressing_clients.update'),
            'canDelete' => $this->can('pressing_clients.delete'),
        ])->layout('layouts.app', ['title' => 'Nos Clients']);
    }
}
