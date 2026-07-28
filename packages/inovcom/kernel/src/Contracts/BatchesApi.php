<?php

namespace InovCom\Kernel\Contracts;

use Illuminate\Support\Collection;

/**
 * API contract for Batches (Lots) module.
 * Other modules use this to consume/record stock by batch without coupling.
 */
interface BatchesApi
{
    /**
     * Get batches for an item (FEFO: first expiry first). Only batches with quantity > 0.
     */
    public function getBatchesForItem(int $itemId, bool $fefo = true): Collection;

    /**
     * Consume quantity from batches (FEFO). Updates batch quantities and batch_movements.
     * Returns list of [batch_id => quantity_consumed].
     *
     * @param string|null $referenceType e.g. 'sale_line', 'adjustment'
     * @param int|null $referenceId
     */
    public function consumeFromBatches(int $itemId, float $quantity, ?string $referenceType = null, ?int $referenceId = null): array;

    /**
     * Record receipt: create or update batch and movement (purchase receipt).
     *
     * @return object Batch model
     */
    public function recordReceipt(int $itemId, string $batchNumber, \Carbon\CarbonInterface $expiryDate, float $quantity, string $referenceType, int $referenceId): object;

    /**
     * Restore quantity to an existing batch (sale return, reversal).
     */
    public function restoreToBatch(int $batchId, float $quantity, ?string $referenceType = null, ?int $referenceId = null): void;

    /**
     * Check if Batches module is active (e.g. has tables).
     */
    public function isAvailable(): bool;
}
