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
     * Find an item by reference (sku)
     */
    public function findItem(string $sku): ?object;

    /**
     * Find an item by ID
     */
    public function findItemById(int $id): ?object;

    /**
     * Get item price.
     *
     * ERP passes ?int $unitId (unit-specific price).
     * BAT passes string $tier ('retail'|'semi_wholesale'|'wholesale').
     * Implementations should handle their own second-arg semantics.
     */
    public function getItemPrice(string $sku, mixed $context = null): ?float;

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
     * Check if item exists by reference (sku)
     */
    public function itemExists(string $sku): bool;
}
