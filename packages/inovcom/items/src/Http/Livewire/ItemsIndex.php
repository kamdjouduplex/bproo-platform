<?php

namespace InovCom\Items\Http\Livewire;

use App\Events\ModuleEvents\ItemDeleted;
use App\Services\TenantManager;
use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use InovCom\Items\Models\Item;
use InovCom\Items\Services\ItemsDeleteService;
use InovCom\Items\Services\ItemsListColumnService;
use Illuminate\Support\Facades\Event;
use Livewire\Component;
use Livewire\WithPagination;

class ItemsIndex extends Component
{
    use AuthorizesItemAccess;
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    public function delete(int $itemId): void
    {
        if (!$this->canItem('items.delete')) {
            notify()->error('Permission refusée.');
            return;
        }

        $item = Item::find($itemId);
        if (!$item) {
            notify()->error('Article introuvable.');
            return;
        }

        try {
            app(ItemsDeleteService::class)->delete($item);
        } catch (\RuntimeException $e) {
            notify()->error($e->getMessage());
            return;
        }

        $tenant = app(TenantManager::class)->tenant();
        if ($tenant) {
            Event::dispatch(new ItemDeleted($itemId, $tenant));
        }

        notify()->success('Article supprimé.');
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth('tenant')->user();
        $columnService = app(ItemsListColumnService::class);
        $visibleColumns = $columnService->visibleColumnsForUser($user);

        $items = Item::query()
            ->with(['category', 'brand', 'unit', 'unitPrices.unit'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('sku', 'like', '%' . $this->search . '%')
                        ->orWhere('barcode', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-items::livewire.items.index')
            ->layout('layouts.app', [
                'title' => 'Articles',
                'subtitle' => 'Catalogue produit',
            ])
            ->with([
                'items' => $items,
                'visibleColumns' => $visibleColumns,
                'canViewCost' => $this->canItem('items.view_cost'),
                'canConfigureList' => $this->canItem('items.configure_list'),
                'canCreate' => $this->canItem('items.create'),
                'canUpdate' => $this->canItem('items.update'),
                'canDelete' => $this->canItem('items.delete'),
            ]);
    }
}
