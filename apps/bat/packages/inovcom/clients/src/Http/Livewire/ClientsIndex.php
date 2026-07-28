<?php

namespace InovCom\Clients\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Clients\Models\Client;
use Livewire\Component;
use Livewire\WithPagination;

class ClientsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('clients.view');
    }

    public string $search = '';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $client = Client::findOrFail($id);
        $client->delete();
        session()->flash('message', __('Client supprimé.'));
    }

    public function render()
    {
        $clients = Client::query()
            ->when($this->search !== '', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->ordered()
            ->paginate($this->perPage);

        return view('inovcom-clients::livewire.clients.index')
            ->layout('layouts.app', [
                'title' => __('Clients'),
                'subtitle' => __('Fiche client, contacts et historique. Centre du système.'),
            ])
            ->with(['clients' => $clients]);
    }
}
