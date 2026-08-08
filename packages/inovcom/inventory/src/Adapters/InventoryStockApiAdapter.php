<?php

namespace InovCom\Stock\Adapters;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use InovCom\Kernel\Contracts\StockApi;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryStockApiAdapter implements StockApi
{
    public function __construct(
        private StockService $stockService
    ) {}

    public function getAvailableQuantity(int $itemId, ?int $storeId = null): float
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels')) {
            return 0.0;
        }

        return $this->stockService->getAvailableQuantity($itemId, $storeId);
    }

    public function hasStock(int $itemId, float $quantity, ?int $storeId = null): bool
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels')) {
            return false;
        }

        return $this->stockService->hasStock($itemId, $quantity, $storeId);
    }

    public function getLowStockItems(int $limit = 50): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels') || ! Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $rows = $this->stockService->getLowStockItems()->take($limit);

        return $rows->map(function ($stockLevel): array {
            return [
                'item_name' => (string) ($stockLevel->item?->name ?? '-'),
                'item_sku' => $stockLevel->item?->sku,
                'available' => (float) $stockLevel->available_quantity,
                'reorder_point' => $stockLevel->reorder_point !== null ? (float) $stockLevel->reorder_point : null,
            ];
        })->values()->all();
    }

    public function getOutOfStockItems(int $limit = 50): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels') || ! Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $storeId = $this->resolveStoreId();
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('stock_levels', 'store_id');

        $rows = DB::connection('tenant')
            ->table('stock_levels')
            ->join('items', 'stock_levels.item_id', '=', 'items.id')
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('stock_levels.store_id', $storeId))
            ->where('stock_levels.available_quantity', '<=', 0)
            ->select(
                'items.name as item_name',
                'items.sku as item_sku',
                'stock_levels.available_quantity as available',
                'stock_levels.reorder_point as reorder_point'
            )
            ->orderBy('items.name')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r): array {
            return [
                'item_name' => (string) ($r->item_name ?? '-'),
                'item_sku' => $r->item_sku,
                'available' => (float) $r->available,
                // Keep "Seuil" empty in the UI for out-of-stock mode.
                'reorder_point' => null,
            ];
        })->all();
    }

    private function resolveStoreId(?int $storeId = null): ?int
    {
        if ($storeId !== null) {
            return $storeId;
        }

        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();

        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }
}

