<?php

namespace InovCom\Clients\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Clients\Models\Client;
use Livewire\Component;
use Livewire\WithPagination;

class ClientAccount extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search = '';
    public string $code   = '';
    public string $type   = '';
    public string $status = '';

    public function mount(): void
    {
        $this->tenantAuthorize('clients.view');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCode():   void { $this->resetPage(); }
    public function updatedType():   void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'code', 'type', 'status']);
        $this->resetPage();
    }

    public function render()
    {
        $hasFilters = $this->search || $this->code || $this->type || $this->status;

        if ($hasFilters) {
            $clients = Client::query()
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name',  'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")
                ))
                ->when($this->code, fn ($q) => $q->where(fn ($q) => $q
                    ->where('code', 'like', "%{$this->code}%")
                    ->when(is_numeric($this->code), fn ($q) => $q->orWhere('id', (int) $this->code))
                ))
                ->when($this->type,   fn ($q) => $q->where('type', $this->type))
                ->when($this->status === 'active',   fn ($q) => $q->where('is_active', true))
                ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
                ->ordered()
                ->paginate(15);
        } else {
            $clients = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return view('inovcom-clients::livewire.clients.account', compact('clients', 'hasFilters'))
            ->layout('layouts.app', [
                'title'    => __('Compte Client'),
                'subtitle' => __('Recherchez un client pour accéder à sa vue 360°'),
            ]);
    }
}
