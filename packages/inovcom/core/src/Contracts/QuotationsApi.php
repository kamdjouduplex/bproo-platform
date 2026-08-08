<?php

namespace InovCom\Kernel\Contracts;

/**
 * API contract for Quotations module — report reads.
 */
interface QuotationsApi
{
    /**
     * @return array{
     *   count: int,
     *   total: float,
     *   by_status: array<string, array{count: int, total: float}>,
     *   accepted_count: int,
     *   accepted_total: float
     * }
     */
    public function getPeriodSummary(string $startDate, string $endDate): array;

    /**
     * @return array<int, array{client_id: int, client_name: string, quotation_total: float, quotation_count: int}>
     */
    public function getClientTotalsInPeriod(string $startDate, string $endDate): array;
}
