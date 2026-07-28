<?php

namespace InovCom\Stock\Http\Livewire;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Stock\Exports\StockExcelExporter;
use InovCom\Stock\Services\StorageLocationService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filter = 'all'; // all, low_stock, in_stock, out_of_stock

    public int $perPage = 20;

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        $this->perPage = max(10, min(100, (int) $value));
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function exportExcel(): StreamedResponse
    {
        $locationService = app(StorageLocationService::class);
        $storeId = $this->resolveStoreId();
        $locationsEnabled = $locationService->isAvailable();

        $items = $this->buildQuery($storeId, $locationsEnabled)->limit(5000)->get();

        foreach ($items as $item) {
            if ($locationsEnabled) {
                $item->location_code = $locationService->codesForItem($item->id, $storeId) ?: null;
            }
        }

        $headers = ['Référence', 'Désignation', 'Stock disponible', 'Stock total', "Stock d'alerte", 'Statut'];
        if ($locationsEnabled) {
            $headers[] = 'Emplacement';
        }

        $title = 'Stock — ' . $this->filterLabel();
        if (trim($this->search) !== '') {
            $title .= ' — « ' . trim($this->search) . ' »';
        }

        return StockExcelExporter::download(
            'stock-' . now()->format('Ymd_His') . '.xls',
            $headers,
            StockExcelExporter::stockRows($items, $locationsEnabled),
            $title
        );
    }

    public function render()
    {
        $locationService = app(StorageLocationService::class);
        $storeId = $this->resolveStoreId();
        $locationsEnabled = $locationService->isAvailable();

        $items = $this->buildQuery($storeId, $locationsEnabled)->paginate($this->perPage);

        foreach ($items as $item) {
            if ($locationsEnabled) {
                $item->location_code = $locationService->codesForItem($item->id, $storeId) ?: null;
            }
        }

        $filterLabel = $this->filterLabel();
        $totalItems = $items->total();

        return view('inovcom-stock::livewire.stock.index')
            ->layout('layouts.app', [
                'title' => 'Stock',
                'subtitle' => sprintf(
                    '%s article(s) · %s',
                    number_format($totalItems, 0, ',', ' '),
                    $filterLabel
                ),
            ])
            ->with([
                'items' => $items,
                'locationsEnabled' => $locationsEnabled,
                'filterLabel' => $filterLabel,
            ]);
    }

    private function buildQuery(?int $storeId, bool $locationsEnabled)
    {
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('stock_levels', 'store_id');

        $stockSub = DB::connection('tenant')
            ->table('stock_levels')
            ->select('item_id', 'quantity', 'available_quantity', 'reorder_point')
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('store_id', $storeId));

        return Item::query()
            ->leftJoinSub($stockSub, 'stock_levels', 'items.id', '=', 'stock_levels.item_id')
            ->select('items.*', 'stock_levels.quantity', 'stock_levels.available_quantity', 'stock_levels.reorder_point')
            ->when($this->search !== '', function ($query) use ($locationsEnabled, $storeId) {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term, $locationsEnabled, $storeId) {
                    $q->where('items.name', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term);
                    if ($locationsEnabled) {
                        $q->orWhereIn('items.id', function ($sub) use ($term, $storeId) {
                            $sub->select('item_storage_locations.item_id')
                                ->from('item_storage_locations')
                                ->join('storage_locations', 'storage_locations.id', '=', 'item_storage_locations.storage_location_id')
                                ->where(function ($l) use ($term) {
                                    $l->where('storage_locations.zone', 'like', $term)
                                        ->orWhere('storage_locations.code', 'like', $term)
                                        ->orWhere('storage_locations.aisle', 'like', $term)
                                        ->orWhere('storage_locations.shelf', 'like', $term);
                                });
                            if (Schema::connection('tenant')->hasColumn('storage_locations', 'store_id') && $storeId) {
                                $sub->where('storage_locations.store_id', $storeId);
                            }
                        });
                    }
                });
            })
            ->when($this->filter !== 'all', fn ($query) => $this->applyStatusFilter($query, $storeId, $hasStoreColumn))
            ->orderBy('items.name');
    }

    private function resolveStoreId(): ?int
    {
        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();

        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }

    private function filterLabel(): string
    {
        return match ($this->filter) {
            'low_stock' => 'Stock faible',
            'in_stock' => 'En stock',
            'out_of_stock' => 'Rupture',
            default => 'Tous les articles',
        };
    }

    /**
     * Filtres alignés sur les badges du tableau (rupture / stock faible / en stock).
     */
    private function applyStatusFilter($query, ?int $storeId, bool $hasStoreColumn): void
    {
        $scopeStockLevel = function ($sub) use ($storeId, $hasStoreColumn) {
            $sub->from('stock_levels')
                ->whereColumn('stock_levels.item_id', 'items.id')
                ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('stock_levels.store_id', $storeId));
        };

        match ($this->filter) {
            'in_stock' => $query->whereExists(function ($sub) use ($scopeStockLevel) {
                $scopeStockLevel($sub);
                $sub->whereRaw('COALESCE(stock_levels.available_quantity, 0) > 0')
                    ->where(function ($q) {
                        $q->whereNull('stock_levels.reorder_point')
                            ->orWhereRaw('COALESCE(stock_levels.available_quantity, 0) > stock_levels.reorder_point');
                    });
            }),
            'low_stock' => $query->whereExists(function ($sub) use ($scopeStockLevel) {
                $scopeStockLevel($sub);
                $sub->whereNotNull('stock_levels.reorder_point')
                    ->whereRaw('COALESCE(stock_levels.available_quantity, 0) > 0')
                    ->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= stock_levels.reorder_point');
            }),
            'out_of_stock' => $query->where(function ($q) use ($scopeStockLevel) {
                $q->whereNotExists(function ($sub) use ($scopeStockLevel) {
                    $scopeStockLevel($sub);
                })->orWhereExists(function ($sub) use ($scopeStockLevel) {
                    $scopeStockLevel($sub);
                    $sub->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= 0');
                });
            }),
            default => null,
        };
    }
}
