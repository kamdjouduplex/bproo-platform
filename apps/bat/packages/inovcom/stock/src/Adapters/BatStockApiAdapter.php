<?php

namespace InovCom\Stock\Adapters;

use App\Services\StoreContextService;
use InovCom\Kernel\Contracts\StockApi;
use InovCom\Stock\Models\Warehouse;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BatStockApiAdapter implements StockApi
{
    public function __construct(
        private StockService $stockService
    ) {}

    public function getAvailableQuantity(int $itemId, ?int $storeId = null): float
    {
        if (! Schema::connection('tenant')->hasTable('stock_movements') || ! Schema::connection('tenant')->hasTable('products')) {
            return 0.0;
        }

        $warehouseId = $this->resolveWarehouseId($storeId);

        return $this->stockService->getCurrentStock($itemId, $warehouseId);
    }

    public function hasStock(int $itemId, float $quantity, ?int $storeId = null): bool
    {
        return $this->getAvailableQuantity($itemId, $storeId) >= $quantity;
    }

    public function getLowStockItems(int $limit = 50): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_movements') || ! Schema::connection('tenant')->hasTable('products')) {
            return [];
        }

        $warehouseId = $this->resolveWarehouseId(null);

        $rows = DB::connection('tenant')
            ->table('products as p')
            ->leftJoin('stock_movements as sm', function ($join) use ($warehouseId) {
                $join->on('sm.product_id', '=', 'p.id');
                if ($warehouseId !== null) {
                    $join->where('sm.warehouse_id', '=', $warehouseId);
                }
            })
            ->select(
                'p.name as item_name',
                'p.code as item_sku',
                'p.min_stock_alert as reorder_point'
            )
            ->selectRaw('COALESCE(SUM(sm.quantity), 0) as available')
            ->where('p.min_stock_alert', '>', 0)
            ->groupBy('p.id', 'p.name', 'p.code', 'p.min_stock_alert')
            ->havingRaw('COALESCE(SUM(sm.quantity), 0) <= p.min_stock_alert')
            ->orderBy('available')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r): array {
            return [
                'item_name' => (string) ($r->item_name ?? '-'),
                'item_sku' => $r->item_sku,
                'available' => (float) $r->available,
                'reorder_point' => $r->reorder_point !== null ? (float) $r->reorder_point : null,
            ];
        })->all();
    }

    public function getOutOfStockItems(int $limit = 50): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_movements') || ! Schema::connection('tenant')->hasTable('products')) {
            return [];
        }

        $warehouseId = $this->resolveWarehouseId(null);

        $rows = DB::connection('tenant')
            ->table('products as p')
            ->leftJoin('stock_movements as sm', function ($join) use ($warehouseId) {
                $join->on('sm.product_id', '=', 'p.id');
                if ($warehouseId !== null) {
                    $join->where('sm.warehouse_id', '=', $warehouseId);
                }
            })
            ->select(
                'p.name as item_name',
                'p.code as item_sku',
                'p.min_stock_alert as reorder_point'
            )
            ->selectRaw('COALESCE(SUM(sm.quantity), 0) as available')
            ->groupBy('p.id', 'p.name', 'p.code', 'p.min_stock_alert')
            ->havingRaw('COALESCE(SUM(sm.quantity), 0) <= 0')
            ->orderBy('available')
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

    private function resolveWarehouseId(?int $storeId = null): ?int
    {
        if ($storeId !== null) {
            return $storeId;
        }

        $fromContext = app(StoreContextService::class)->currentStoreId();
        if ($fromContext !== null) {
            return $fromContext;
        }

        if (! Schema::connection('tenant')->hasTable('warehouses')) {
            return null;
        }

        return Warehouse::on('tenant')
            ->active()
            ->ordered()
            ->value('id');
    }
}

