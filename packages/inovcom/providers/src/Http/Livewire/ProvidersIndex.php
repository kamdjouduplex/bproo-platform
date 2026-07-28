<?php

namespace InovCom\Providers\Http\Livewire;

use InovCom\Providers\Models\Provider;
use Livewire\Component;
use Livewire\WithPagination;

class ProvidersIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    public function delete(int $providerId): void
    {
        Provider::whereKey($providerId)->delete();
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $providers = Provider::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('code', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-providers::livewire.providers.index')
            ->layout('layouts.app', [
                'title' => 'Fournisseurs',
                'subtitle' => 'Gestion des fournisseurs',
            ])
            ->with(['providers' => $providers]);
    }
}
