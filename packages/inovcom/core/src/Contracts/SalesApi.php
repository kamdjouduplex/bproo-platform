<?php

namespace InovCom\Kernel\Contracts;

/**
 * API contract for Sales (POS) module — report / cross-module reads.
 */
interface SalesApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float;

    public function getPeriodCount(string $startDate, string $endDate): int;

    public function getCostOfGoodsSold(string $startDate, string $endDate): float;

    /**
     * @return array<string, float>
     */
    public function getDailySalesTrend(string $startDate, string $endDate): array;

    /**
     * @return array<string, float>
     */
    public function getDailyCogsTrend(string $startDate, string $endDate): array;

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function getTopProductsByRevenue(string $startDate, string $endDate, int $limit = 10): array;

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function getTopProductsByQuantity(string $startDate, string $endDate, int $limit = 10): array;

    /**
     * @return array<int, array{item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function getTopProductsForClient(
        string $startDate,
        string $endDate,
        int $clientId,
        int $limit = 10,
        string $sortBy = 'revenue'
    ): array;

    /**
     * @return array<int, array{client_id: int, client_name: string, pos_revenue: float, pos_sale_count: int}>
     */
    public function getClientRevenueInPeriod(string $startDate, string $endDate): array;

    public function getDistinctClientsCount(string $startDate, string $endDate): int;

    public function hasStoreDimension(): bool;

    /**
     * @return array<int, array{store_id: int, store_name: string, sales_count: int, sales_total: float}>
     */
    public function getStorePerformance(string $startDate, string $endDate): array;

    /**
     * @return array<int, array{sale_date: string, total: float, client_name: string|null}>
     */
    public function getTopSalesByTotal(string $startDate, string $endDate, int $limit = 10): array;

    /**
     * @return array<int, array{sale_number: string|null, sale_date: string|null, total: float, client_name: string|null}>
     */
    public function listSales(
        string $startDate,
        string $endDate,
        ?int $clientId = null,
        int $limit = 100
    ): array;

    /**
     * Align POS credit with a linked debt after collection (or return).
     * Does not post to caisse — the debt payment already does.
     *
     * @param  array<int, array{amount: float, method: string, reference?: string|null, user_id?: int|null}>  $collections
     */
    public function syncLinkedDebtCollections(
        int $saleId,
        float $outstandingCredit,
        string $debtReference,
        array $collections
    ): bool;
}
