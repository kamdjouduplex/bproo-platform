<?php

namespace InovCom\Achats\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use InovCom\Achats\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;

class SuppliersIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('achats.view');
    }

    public string $search = '';
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        if (function_exists('notify')) {
            notify()->success(__('Fournisseur supprimé.'));
        }
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%')
                        ->orWhere('contact_name', 'like', '%' . $this->search . '%');
                });
            })
            ->ordered()
            ->paginate($this->perPage);

        return view('inovcom-achats::livewire.suppliers.index')
            ->layout('layouts.app', [
                'title' => __('Fournisseurs'),
                'subtitle' => __('Gestion des fournisseurs.'),
            ])
            ->with(['suppliers' => $suppliers]);
    }
}
