<?php

namespace InovCom\Kernel\Contracts;

interface DebtsApi
{
    public function isAvailable(): bool;

    /**
     * @return array{receivables_total: float, collected_in_period: float, overdue_count: int, overdue_total: float}
     */
    public function getSummary(string $startDate, string $endDate): array;

    /**
     * Create (or reuse) a debt for the credit portion of a POS sale.
     *
     * @return array{id: int, reference: string, balance: float, status: string}|null
     */
    public function recordCreditSale(
        int $clientId,
        float $amount,
        int $saleId,
        string $saleNumber,
        ?int $userId = null,
        ?string $openedAt = null
    ): ?array;

    /**
     * Reduce the sale-linked debt when a credit payment is refunded via a return.
     */
    public function applyCreditSaleReturn(int $saleId, float $amount): bool;

    /**
     * @return array{id: int, reference: string, balance: float, status: string, total_amount: float}|null
     */
    public function findBySaleId(int $saleId): ?array;

    /**
     * Replay debt collections onto the linked POS payments (idempotent).
     *
     * @param  array<int, int>  $saleIds
     */
    public function syncLinkedSales(array $saleIds): void;
}
