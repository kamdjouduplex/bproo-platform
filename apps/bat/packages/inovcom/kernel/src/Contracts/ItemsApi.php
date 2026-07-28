<?php

namespace InovCom\Kernel\Contracts;

use Illuminate\Support\Collection;

/**
 * API contract for Items module
 * 
 * This interface allows other modules to interact with Items
 * without directly accessing Items models, ensuring loose coupling.
 */
interface ItemsApi
{
    /**
     * Find an item by SKU
     */
    public function findItem(string $sku): ?object;

    /**
     * Find an item by ID
     */
    public function findItemById(int $id): ?object;

    /**
     * Get item price for a specific tier
     * 
     * @param string $sku Item SKU
     * @param string $tier Price tier: 'retail', 'semi_wholesale', 'wholesale'
     * @return float|null Price or null if item not found
     */
    public function getItemPrice(string $sku, string $tier = 'retail'): ?float;

    /**
     * Check if an item is active
     */
    public function isItemActive(string $sku): bool;

    /**
     * Get item cost
     */
    public function getItemCost(string $sku): ?float;

    /**
     * Get all active items
     */
    public function getActiveItems(): Collection;

    /**
     * Check if item exists by SKU
     */
    public function itemExists(string $sku): bool;
}
