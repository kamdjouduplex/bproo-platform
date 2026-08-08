<?php

namespace InovCom\Kernel\Contracts;

/**
 * API contract for Purchasing module
 *
 * Lets reports and other modules read purchase orders without depending on
 * ERP (`purchases`) vs BAT (`achats`) schema differences.
 */
interface PurchasingApi
{
    /**
     * Find a purchase order by ID.
     */
    public function findPurchaseOrder(int $id): ?object;

    /**
     * Find a purchase order by business number (ERP: order_number, BAT: code).
     */
    public function findPurchaseOrderByNumber(string $number): ?object;

    /**
     * Check if a purchase order exists.
     */
    public function purchaseOrderExists(int $id): bool;

    /**
     * Purchase order grand total (ERP: total, BAT: total_ht).
     */
    public function getTotal(int $purchaseOrderId): float;

    /**
     * Sum of received purchase orders in a date range.
     * ERP uses order_date + status=received; BAT uses received_at/ordered_at + status=received.
     */
    public function getPeriodReceivedTotal(string $startDate, string $endDate): float;

    /**
     * Period purchase KPIs for reporting.
     *
     * @return array{
     *   received_count: int,
     *   received_total: float,
     *   draft_count: int,
     *   open_count: int,
     *   cancelled_count: int,
     *   by_status: array<string, array{count: int, total: float}>
     * }
     */
    public function getPeriodSummary(string $startDate, string $endDate): array;
}
