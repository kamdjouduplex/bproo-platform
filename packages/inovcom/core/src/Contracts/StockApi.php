<?php

namespace InovCom\Kernel\Contracts;

/**
 * API contract for Stock module
 *
 * This interface standardizes access to stock quantities and stock alerts across
 * verticals (ERP, BAT, etc.) without requiring callers to know the underlying
 * schema or implementation details.
 */
interface StockApi
{
    /**
     * Get available quantity for an item (aka "disponible").
     *
     * @param  int  $itemId  Item id (ERP: items.id, BAT: products.id)
     * @param  int|null  $storeId  ERP store_id / BAT warehouse_id (optional)
     */
    public function getAvailableQuantity(int $itemId, ?int $storeId = null): float;

    /**
     * Check if an item has at least the given quantity available.
     */
    public function hasStock(int $itemId, float $quantity, ?int $storeId = null): bool;

    /**
     * Get stock-low items (quantity <= reorder/alert threshold).
     *
     * @return array<int, array{
     *     item_name: string,
     *     item_sku: string|null,
     *     available: float,
     *     reorder_point: float|null
     * }>
     */
    public function getLowStockItems(int $limit = 50): array;

    /**
     * Get out-of-stock items (quantity <= 0).
     *
     * @return array<int, array{
     *     item_name: string,
     *     item_sku: string|null,
     *     available: float,
     *     reorder_point: float|null
     * }>
     */
    public function getOutOfStockItems(int $limit = 50): array;
}

