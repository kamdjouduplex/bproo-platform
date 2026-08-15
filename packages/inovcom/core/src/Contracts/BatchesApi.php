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
     * By default excludes expired lots (expiry_date < today) so they cannot be sold.
     */
    public function getBatchesForItem(int $itemId, bool $fefo = true, bool $excludeExpired = true): Collection;

    /**
     * Sum of sellable (non-expired) quantity for an item.
     */
    public function sellableQuantity(int $itemId): float;

    /**
     * Preview FEFO allocation without consuming stock.
     * Returns list of rows: batch_id, batch_number, expiry_date (Y-m-d), quantity.
     *
     * @return list<array{batch_id: int, batch_number: string, expiry_date: string, quantity: float}>
     */
    public function previewAllocation(int $itemId, float $quantity, ?int $preferredBatchId = null): array;

    /**
     * Consume quantity from batches (FEFO, non-expired only).
     * If $preferredBatchId is set, consume only from that lot (must be sellable).
     * Throws \RuntimeException if not enough sellable stock.
     * Returns list of [batch_id => quantity_consumed].
     *
     * @param  string|null  $referenceType  e.g. 'sale', 'adjustment'
     */
    public function consumeFromBatches(
        int $itemId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $preferredBatchId = null
    ): array;

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
     * Write off an expired lot: zero batch qty, decrease item stock, audit movements.
     * Only allowed when expiry_date < today and quantity > 0.
     *
     * @return array{batch_id: int, item_id: int, quantity: float, batch_number: string, loss_record_id: int|null}
     */
    public function writeOffExpiredBatch(int $batchId, ?string $notes = null): array;

    /**
     * Correct the expiry date of an existing batch (typo / wrong entry).
     *
     * @return object Batch model
     */
    public function updateExpiryDate(int $batchId, \Carbon\CarbonInterface $expiryDate): object;

    /**
     * Check if Batches module is active (e.g. has tables).
     */
    public function isAvailable(): bool;
}
