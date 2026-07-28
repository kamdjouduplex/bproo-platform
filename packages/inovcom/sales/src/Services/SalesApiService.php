<?php

namespace InovCom\Sales\Services;

use InovCom\Sales\Models\Sale;
use Illuminate\Support\Collection;

/**
 * Simple API service for Sales module
 * Used by other modules (like Reporting) to interact with sales
 */
class SalesApiService
{
    public function findSale(int $id): ?object
    {
        return Sale::on('tenant')->with(['lines', 'payments', 'client'])->find($id);
    }

    public function findSaleByNumber(string $saleNumber): ?object
    {
        return Sale::on('tenant')
            ->where('sale_number', $saleNumber)
            ->first();
    }

    public function getSalesByDateRange(string $startDate, string $endDate): Collection
    {
        return Sale::on('tenant')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->orderBy('sale_date', 'desc')
            ->get();
    }

    public function getSalesByClient(int $clientId): Collection
    {
        return Sale::on('tenant')
            ->where('client_id', $clientId)
            ->orderBy('sale_date', 'desc')
            ->get();
    }

    public function getTotalSalesByDate(string $date): float
    {
        return (float) Sale::on('tenant')
            ->whereDate('sale_date', $date)
            ->sum('total');
    }
}
