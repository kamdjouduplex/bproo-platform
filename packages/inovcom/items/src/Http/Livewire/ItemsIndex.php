<?php

namespace InovCom\Items\Http\Livewire;

use App\Events\ModuleEvents\ItemDeleted;
use App\Services\TenantManager;
use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use InovCom\Items\MedicamentsModule;
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

    public function mount(): void
    {
        if (function_exists('items_is_pharmacy_catalog') && items_is_pharmacy_catalog()) {
            MedicamentsModule::syncDefaultBrands();
        }
    }

    public function delete(int $itemId): void
    {
        if (!$this->canItem('items.delete')) {
            notify()->error('Permission refusée.');
            return;
        }

        $item = Item::find($itemId);
        if (!$item) {
            notify()->error(ucfirst(items_catalog_noun()['singular']).' introuvable.');
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

        notify()->success(ucfirst(items_catalog_noun()['singular']).' supprimé.');
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
        $noun = items_catalog_noun();

        $items = Item::query()
            ->with(['category', 'brand', 'unit', 'unitPrices.unit'])
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-items::livewire.items.index')
            ->layout('layouts.app', [
                'title' => $noun['title'],
                'subtitle' => $noun['subtitle'],
            ])
            ->with([
                'items' => $items,
                'visibleColumns' => $visibleColumns,
                'canViewCost' => $this->canItem('items.view_cost'),
                'canConfigureList' => $this->canItem('items.configure_list'),
                'canCreate' => $this->canItem('items.create'),
                'canUpdate' => $this->canItem('items.update'),
                'canDelete' => $this->canItem('items.delete'),
                'isPharmacyCatalog' => items_is_pharmacy_catalog(),
                'catalogNoun' => $noun,
            ]);
    }
}
