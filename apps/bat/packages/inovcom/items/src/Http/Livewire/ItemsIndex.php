<?php

namespace InovCom\Items\Http\Livewire;

use App\Events\ModuleEvents\ItemDeleted;
use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Items\Models\Item;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\WithPagination;

class ItemsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public function mount(): void
    {
        $this->tenantAuthorize('items.view');
    }

    public string $search = '';
    public int $perPage = 10;

    public function delete(int $itemId): void
    {
        Item::whereKey($itemId)->delete();
        
        // Dispatch event for cross-module communication
        $tenant = app(TenantManager::class)->tenant();
        if ($tenant) {
            Event::dispatch(new ItemDeleted($itemId, $tenant));
        }
        
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $items = Item::query()
            ->with(['category', 'unit'])
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-items::livewire.items.index')
            ->layout('layouts.app', [
                'title' => 'Articles',
                'subtitle' => 'Catalogue produit',
            ])
            ->with(['items' => $items]);
    }
}
