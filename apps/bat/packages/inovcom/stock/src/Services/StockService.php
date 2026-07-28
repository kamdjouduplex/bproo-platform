<?php

namespace InovCom\Stock\Services;

use InovCom\Stock\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Record a stock-in movement (positive quantity).
     */
    public function addStock(
        int    $productId,
        int    $warehouseId,
        float  $quantity,
        string $referenceType = 'adjustment',
        ?int   $referenceId   = null,
        ?string $notes        = null
    ): StockMovement {
        return StockMovement::on('tenant')->create([
            'product_id'     => $productId,
            'warehouse_id'   => $warehouseId,
            'quantity'       => abs($quantity),
            'type'           => 'IN',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'user_id'        => $this->resolveUserId(),
            'notes'          => $notes,
        ]);
    }

    /**
     * Record a stock-out movement (negative quantity stored in DB).
     */
    public function removeStock(
        int    $productId,
        int    $warehouseId,
        float  $quantity,
        string $referenceType = 'adjustment',
        ?int   $referenceId   = null,
        ?string $notes        = null
    ): StockMovement {
        return StockMovement::on('tenant')->create([
            'product_id'     => $productId,
            'warehouse_id'   => $warehouseId,
            'quantity'       => -abs($quantity),
            'type'           => 'OUT',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'user_id'        => $this->resolveUserId(),
            'notes'          => $notes,
        ]);
    }

    /**
     * Transfer stock between warehouses: creates one OUT + one IN movement atomically.
     * Returns [outMovement, inMovement].
     */
    public function transferStock(
        int    $productId,
        int    $fromWarehouseId,
        int    $toWarehouseId,
        float  $quantity,
        ?string $notes = null
    ): array {
        $userId = $this->resolveUserId();

        return DB::connection('tenant')->transaction(function () use (
            $productId, $fromWarehouseId, $toWarehouseId, $quantity, $userId, $notes
        ) {
            $out = StockMovement::on('tenant')->create([
                'product_id'     => $productId,
                'warehouse_id'   => $fromWarehouseId,
                'quantity'       => -abs($quantity),
                'type'           => 'TRANSFER',
                'reference_type' => 'transfer',
                'user_id'        => $userId,
                'notes'          => $notes,
            ]);

            $in = StockMovement::on('tenant')->create([
                'product_id'     => $productId,
                'warehouse_id'   => $toWarehouseId,
                'quantity'       => abs($quantity),
                'type'           => 'TRANSFER',
                'reference_type' => 'transfer',
                'reference_id'   => $out->id,
                'user_id'        => $userId,
                'notes'          => $notes,
            ]);

            return [$out, $in];
        });
    }

    /**
     * Compute current stock as SUM(quantity) — never stored statically.
     * Pass warehouseId to scope to a specific location.
     */
    public function getCurrentStock(int $productId, ?int $warehouseId = null): float
    {
        $query = StockMovement::on('tenant')->where('product_id', $productId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (float) $query->sum('quantity');
    }

    /**
     * Return aggregated stock levels for all product/warehouse combinations.
     */
    public function getAllStockLevels(?int $warehouseId = null): \Illuminate\Support\Collection
    {
        $query = StockMovement::on('tenant')
            ->select('product_id', 'warehouse_id', DB::raw('SUM(quantity) as current_stock'))
            ->groupBy('product_id', 'warehouse_id')
            ->with(['product', 'warehouse']);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    private function resolveUserId(): ?int
    {
        try {
            return auth('tenant')->id() ?? auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }
}
