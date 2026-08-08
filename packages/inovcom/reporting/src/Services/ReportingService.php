<?php

namespace InovCom\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\DebtsApi;
use InovCom\Kernel\Contracts\ExpensesApi;
use InovCom\Kernel\Contracts\InvoicingApi;
use InovCom\Kernel\Contracts\LossesApi;
use InovCom\Kernel\Contracts\PayrollApi;
use InovCom\Kernel\Contracts\PurchasingApi;
use InovCom\Kernel\Contracts\QuotationsApi;
use InovCom\Kernel\Contracts\SalesApi;
use InovCom\Kernel\Contracts\StockApi;

class ReportingService
{
    private function invoicingApi(): ?InvoicingApi
    {
        return App::bound(InvoicingApi::class) ? app(InvoicingApi::class) : null;
    }

    private function purchasingApi(): ?PurchasingApi
    {
        return App::bound(PurchasingApi::class) ? app(PurchasingApi::class) : null;
    }

    private function stockApi(): ?StockApi
    {
        return App::bound(StockApi::class) ? app(StockApi::class) : null;
    }

    private function salesApi(): ?SalesApi
    {
        return App::bound(SalesApi::class) ? app(SalesApi::class) : null;
    }

    private function quotationsApi(): ?QuotationsApi
    {
        return App::bound(QuotationsApi::class) ? app(QuotationsApi::class) : null;
    }

    private function expensesApi(): ?ExpensesApi
    {
        return App::bound(ExpensesApi::class) ? app(ExpensesApi::class) : null;
    }

    private function lossesApi(): ?LossesApi
    {
        return App::bound(LossesApi::class) ? app(LossesApi::class) : null;
    }

    private function debtsApi(): ?DebtsApi
    {
        return App::bound(DebtsApi::class) ? app(DebtsApi::class) : null;
    }

    private function payrollApi(): ?PayrollApi
    {
        return App::bound(PayrollApi::class) ? app(PayrollApi::class) : null;
    }

    public function hasStoreDimension(): bool
    {
        return $this->salesApi()?->hasStoreDimension() ?? false;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getDateRange(string $period, ?string $periodValue = null): array
    {
        $now = Carbon::now();
        $start = $now->copy();
        $end = $now->copy();

        switch ($period) {
            case 'custom':
                if ($periodValue && str_contains($periodValue, '|')) {
                    [$from, $to] = explode('|', $periodValue, 2);
                    $start = Carbon::parse($from)->startOfDay();
                    $end = Carbon::parse($to)->endOfDay();
                } elseif ($periodValue) {
                    $start = Carbon::parse($periodValue)->startOfDay();
                    $end = Carbon::parse($periodValue)->endOfDay();
                }
                break;
            case 'daily':
                $date = $periodValue ? Carbon::parse($periodValue) : $now;
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();
                break;
            case 'weekly':
                if ($periodValue && preg_match('/^(\d{4})-W(\d{2})$/', $periodValue, $m)) {
                    $start = Carbon::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek();
                    $end = $start->copy()->endOfWeek();
                } else {
                    $start = $now->copy()->startOfWeek();
                    $end = $now->copy()->endOfWeek();
                    if ($periodValue) {
                        $start = Carbon::parse($periodValue)->startOfWeek();
                        $end = $start->copy()->endOfWeek();
                    }
                }
                break;
            case 'monthly':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                if ($periodValue) {
                    $start = Carbon::parse($periodValue)->startOfMonth();
                    $end = $start->copy()->endOfMonth();
                }
                break;
            case 'yearly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                if ($periodValue) {
                    $start = Carbon::parse($periodValue)->startOfYear();
                    $end = $start->copy()->endOfYear();
                }
                break;
        }

        return [$start, $end];
    }

    public function getSalesTotal(Carbon $start, Carbon $end): float
    {
        return $this->salesApi()?->getPeriodTotal($start->format('Y-m-d'), $end->format('Y-m-d')) ?? 0.0;
    }

    public function getSalesCount(Carbon $start, Carbon $end): int
    {
        return $this->salesApi()?->getPeriodCount($start->format('Y-m-d'), $end->format('Y-m-d')) ?? 0;
    }

    public function getAverageSaleAmount(Carbon $start, Carbon $end): float
    {
        $count = $this->getSalesCount($start, $end);

        return $count > 0 ? $this->getSalesTotal($start, $end) / $count : 0;
    }

    public function getExpensesTotal(Carbon $start, Carbon $end): float
    {
        return $this->expensesApi()?->getPeriodTotal($start->format('Y-m-d'), $end->format('Y-m-d')) ?? 0.0;
    }

    public function getLossesTotal(Carbon $start, Carbon $end): float
    {
        return $this->lossesApi()?->getPeriodTotal($start->format('Y-m-d'), $end->format('Y-m-d')) ?? 0.0;
    }

    /**
     * Cost of goods sold for the date range.
     */
    public function getCostOfGoodsSold(Carbon $start, Carbon $end): float
    {
        return $this->salesApi()?->getCostOfGoodsSold($start->format('Y-m-d'), $end->format('Y-m-d')) ?? 0.0;
    }

    /**
     * Benefit (profit) for the date range: sales margin minus confirmed losses.
     */
    public function getBenefit(Carbon $start, Carbon $end): float
    {
        return $this->getSalesTotal($start, $end)
            - $this->getCostOfGoodsSold($start, $end)
            - $this->getLossesTotal($start, $end);
    }

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function getTopSellingProducts(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return $this->salesApi()?->getTopProductsByRevenue(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $limit
        ) ?? [];
    }

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function getTopSellingByQuantity(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return $this->salesApi()?->getTopProductsByQuantity(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $limit
        ) ?? [];
    }

    /**
     * @return array<int, array{tier: string, count: int, total: float}>
     * @deprecated price_tier column was removed; returns empty array.
     */
    public function getSalesByPriceTier(Carbon $start, Carbon $end): array
    {
        return [];
    }

    /**
     * @return array<string, float> [ 'Y-m-d' => total, ... ]
     */
    public function getDailySalesTrend(Carbon $start, Carbon $end): array
    {
        return $this->salesApi()?->getDailySalesTrend(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? [];
    }

    /**
     * @return array<string, float> [ 'Y-m-d' => cogs, ... ]
     */
    public function getDailyCogsTrend(Carbon $start, Carbon $end): array
    {
        return $this->salesApi()?->getDailyCogsTrend(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? $this->fillDailyRange($start, $end, 0.0);
    }

    /**
     * @return array<string, float> [ 'Y-m-d' => value, ... ]
     */
    public function getDailyLossesTrend(Carbon $start, Carbon $end): array
    {
        return $this->lossesApi()?->getDailyTrend(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? $this->fillDailyRange($start, $end, 0.0);
    }

    /**
     * @param  array<string, float>  $existing
     * @return array<string, float>
     */
    private function fillDailyRange(Carbon $start, Carbon $end, float $default, array $existing = []): array
    {
        $curr = $start->copy();
        while ($curr->lte($end)) {
            $d = $curr->format('Y-m-d');
            if (! isset($existing[$d])) {
                $existing[$d] = $default;
            }
            $curr->addDay();
        }
        ksort($existing);

        return $existing;
    }

    /**
     * @return array<int, array{category_name: string, total: float}>
     */
    public function getExpensesByCategory(Carbon $start, Carbon $end): array
    {
        return $this->expensesApi()?->getByCategory(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? [];
    }

    /**
     * @return array<int, array{reason_name: string, total_value: float, total_qty: float}>
     */
    public function getLossesByReason(Carbon $start, Carbon $end): array
    {
        return $this->lossesApi()?->getByReason(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? [];
    }

    /**
     * @return array{receivables_total: float, collected_in_period: float, overdue_count: int, overdue_total: float}
     */
    public function getDebtsSummary(Carbon $start, Carbon $end): array
    {
        return $this->debtsApi()?->getSummary(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? ['receivables_total' => 0, 'collected_in_period' => 0, 'overdue_count' => 0, 'overdue_total' => 0];
    }

    public function getPayrollTotal(Carbon $start, Carbon $end): float
    {
        return $this->payrollApi()?->getPeriodTotal(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? 0.0;
    }

    public function getPurchasesTotal(Carbon $start, Carbon $end): float
    {
        $api = $this->purchasingApi();
        if (! $api) {
            return 0.0;
        }

        return $api->getPeriodReceivedTotal(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }

    /**
     * @return array<int, array{item_name: string, item_sku: string|null, available: float, reorder_point: float|null}>
     */
    public function getLowStockItems(): array
    {
        $api = $this->stockApi();

        return $api ? $api->getLowStockItems(50) : [];
    }

    /**
     * @return array<int, array{item_name: string, item_sku: string|null, available: float}>
     */
    public function getOutOfStockItems(): array
    {
        $api = $this->stockApi();

        return $api ? $api->getOutOfStockItems(50) : [];
    }

    /**
     * @return array<int, array{client_name: string, revenue: float, sale_count: int}>
     */
    public function getTopClientsByRevenue(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return array_map(fn (array $row) => [
            'client_name' => $row['client_name'],
            'revenue' => $row['total_revenue'],
            'sale_count' => $row['pos_sale_count'] + $row['invoice_count'],
        ], $this->getClientPerformanceReport($start, $end, $limit));
    }

    /**
     * CA Facture, CA Vente Direct, devis et part du CA par client sur la période.
     *
     * @return array<int, array{
     *     client_id: int,
     *     client_name: string,
     *     invoice_revenue: float,
     *     invoice_count: int,
     *     pos_revenue: float,
     *     pos_sale_count: int,
     *     quotation_total: float,
     *     quotation_count: int,
     *     total_revenue: float,
     *     revenue_share_pct: float
     * }>
     */
    public function getClientPerformanceReport(Carbon $start, Carbon $end, ?int $limit = null): array
    {
        if (! App::bound(\InovCom\Kernel\Contracts\ClientsApi::class)
            && ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $byClient */
        $byClient = [];

        $ensure = function (int $id, string $name) use (&$byClient): void {
            if (! isset($byClient[$id])) {
                $byClient[$id] = [
                    'client_id' => $id,
                    'client_name' => $name !== '' ? $name : '—',
                    'invoice_revenue' => 0.0,
                    'invoice_count' => 0,
                    'pos_revenue' => 0.0,
                    'pos_sale_count' => 0,
                    'quotation_total' => 0.0,
                    'quotation_count' => 0,
                    'total_revenue' => 0.0,
                    'revenue_share_pct' => 0.0,
                ];
            }
        };

        if ($api = $this->invoicingApi()) {
            foreach ($api->getClientRevenueInPeriod($start->format('Y-m-d'), $end->format('Y-m-d')) as $row) {
                $id = (int) $row['client_id'];
                $ensure($id, (string) $row['client_name']);
                $byClient[$id]['invoice_revenue'] = (float) $row['invoice_revenue'];
                $byClient[$id]['invoice_count'] = (int) $row['invoice_count'];
            }
        }

        if ($salesApi = $this->salesApi()) {
            foreach ($salesApi->getClientRevenueInPeriod($start->format('Y-m-d'), $end->format('Y-m-d')) as $row) {
                $id = (int) $row['client_id'];
                $ensure($id, (string) $row['client_name']);
                $byClient[$id]['pos_revenue'] = (float) $row['pos_revenue'];
                $byClient[$id]['pos_sale_count'] = (int) $row['pos_sale_count'];
            }
        }

        if ($quotesApi = $this->quotationsApi()) {
            foreach ($quotesApi->getClientTotalsInPeriod($start->format('Y-m-d'), $end->format('Y-m-d')) as $row) {
                $id = (int) $row['client_id'];
                $ensure($id, (string) $row['client_name']);
                $byClient[$id]['quotation_total'] = (float) $row['quotation_total'];
                $byClient[$id]['quotation_count'] = (int) $row['quotation_count'];
            }
        }

        $grandTotal = 0.0;
        foreach ($byClient as &$row) {
            $row['total_revenue'] = round((float) $row['invoice_revenue'] + (float) $row['pos_revenue'], 2);
            $grandTotal += (float) $row['total_revenue'];
        }
        unset($row);

        foreach ($byClient as &$row) {
            $row['revenue_share_pct'] = $grandTotal > 0
                ? round(((float) $row['total_revenue'] / $grandTotal) * 100, 2)
                : 0.0;
        }
        unset($row);

        $result = array_values(array_filter(
            $byClient,
            fn (array $row) => (float) $row['total_revenue'] > 0
                || (int) $row['quotation_count'] > 0
        ));

        usort($result, function (array $a, array $b): int {
            $cmp = $b['total_revenue'] <=> $a['total_revenue'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return $b['quotation_total'] <=> $a['quotation_total'];
        });

        if ($limit !== null) {
            $result = array_slice($result, 0, max(1, $limit));
        }

        return $result;
    }

    public function getDistinctClientsCount(Carbon $start, Carbon $end): int
    {
        return $this->salesApi()?->getDistinctClientsCount(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? 0;
    }

    /**
     * @return array<int, array{store_id:int, store_name:string, sales_count:int, sales_total:float}>
     */
    public function getStorePerformance(Carbon $start, Carbon $end): array
    {
        return $this->salesApi()?->getStorePerformance(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ) ?? [];
    }

    /**
     * @return array<int, array{sale_date: string, total: float, client_name: string|null}>
     */
    public function getTopSalesByTotal(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return $this->salesApi()?->getTopSalesByTotal(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $limit
        ) ?? [];
    }

    public function hasQuotationsModule(): bool
    {
        return $this->quotationsApi() !== null;
    }

    public function hasInvoicingModule(): bool
    {
        return $this->invoicingApi() !== null;
    }

    public function hasInvoicePaymentsModule(): bool
    {
        // Payments are exposed via InvoicingApi; adapters return 0/[] when table missing.
        return $this->invoicingApi() !== null;
    }

    /**
     * @return array{
     *   count: int,
     *   total: float,
     *   by_status: array<string, array{count: int, total: float}>,
     *   accepted_count: int,
     *   accepted_total: float,
     *   converted_to_invoice: int
     * }
     */
    public function getQuotationsSummary(Carbon $start, Carbon $end): array
    {
        $empty = [
            'count' => 0,
            'total' => 0.0,
            'by_status' => [],
            'accepted_count' => 0,
            'accepted_total' => 0.0,
            'converted_to_invoice' => 0,
        ];

        $quotesApi = $this->quotationsApi();
        if (! $quotesApi) {
            return $empty;
        }

        $summary = $quotesApi->getPeriodSummary(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );

        $converted = 0;
        if ($api = $this->invoicingApi()) {
            $converted = $api->countConvertedQuotesInPeriod(
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            );
        }

        return array_merge($summary, ['converted_to_invoice' => $converted]);
    }

    /**
     * @return array{
     *   issued_count: int,
     *   issued_total: float,
     *   draft_count: int,
     *   paid_count: int,
     *   paid_total: float,
     *   partial_count: int,
     *   partial_balance: float,
     *   outstanding_balance: float,
     *   cancelled_count: int,
     *   by_status: array<string, array{count: int, total: float}>
     * }
     */
    public function getInvoicesSummary(Carbon $start, Carbon $end): array
    {
        $empty = [
            'issued_count' => 0,
            'issued_total' => 0.0,
            'draft_count' => 0,
            'paid_count' => 0,
            'paid_total' => 0.0,
            'partial_count' => 0,
            'partial_balance' => 0.0,
            'outstanding_balance' => 0.0,
            'cancelled_count' => 0,
            'by_status' => [],
        ];

        $api = $this->invoicingApi();
        if (! $api) {
            return $empty;
        }

        return $api->getPeriodSummary(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }

    public function getInvoicePaymentsTotal(Carbon $start, Carbon $end): float
    {
        $api = $this->invoicingApi();
        if (! $api) {
            return 0.0;
        }

        return $api->getPaymentsTotal(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }

    /**
     * @return array<int, array{method: string, method_label: string, total: float, count: int}>
     */
    public function getInvoicePaymentsByMethod(Carbon $start, Carbon $end): array
    {
        $api = $this->invoicingApi();
        if (! $api) {
            return [];
        }

        return $api->getPaymentsByMethod(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }

    /**
     * @return array{confirmed_count: int, draft_count: int}
     */
    public function getDeliveriesSummary(Carbon $start, Carbon $end): array
    {
        $api = $this->invoicingApi();
        if (! $api) {
            return ['confirmed_count' => 0, 'draft_count' => 0];
        }

        return $api->getDeliveriesSummary(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }

    /**
     * @return array<int, array{invoice_number: string, client_name: string, balance: float, due_date: string|null, status: string}>
     */
    public function getTopOutstandingInvoices(int $limit = 10): array
    {
        $api = $this->invoicingApi();

        return $api ? $api->getTopOutstandingInvoices($limit) : [];
    }

    /**
     * Synthèse financière globale sur la période.
     *
     * @return array<string, mixed>
     */
    public function getFinancialOverview(Carbon $start, Carbon $end): array
    {
        $posSales = $this->getSalesTotal($start, $end);
        $invoices = $this->getInvoicesSummary($start, $end);
        $invoicePayments = $this->getInvoicePaymentsTotal($start, $end);
        $expenses = $this->getExpensesTotal($start, $end);
        $losses = $this->getLossesTotal($start, $end);
        $purchases = $this->getPurchasesTotal($start, $end);
        $payroll = $this->getPayrollTotal($start, $end);
        $cogs = $this->getCostOfGoodsSold($start, $end);
        $benefit = $this->getBenefit($start, $end);

        $encaissementsTotal = $invoicePayments + (float) ($this->getDebtsSummary($start, $end)['collected_in_period'] ?? 0);
        $taxBreakdown = $this->getInvoiceTaxBreakdown($start, $end);

        return [
            'pos_sales' => $posSales,
            'pos_sales_count' => $this->getSalesCount($start, $end),
            'invoices_issued_total' => $invoices['issued_total'],
            'invoices_issued_count' => $invoices['issued_count'],
            'invoice_payments' => $invoicePayments,
            'outstanding_invoices' => $invoices['outstanding_balance'],
            'encaissements_total' => $encaissementsTotal,
            'expenses' => $expenses,
            'losses' => $losses,
            'purchases' => $purchases,
            'payroll' => $payroll,
            'cogs' => $cogs,
            'gross_margin' => max(0, $posSales - $cogs),
            'benefit' => $benefit,
            'charges_total' => $expenses + $losses + $purchases + $payroll,
            'ca_ht' => $taxBreakdown['ca_ht'],
            'tva_total' => $taxBreakdown['tva_total'],
            'other_taxes_total' => $taxBreakdown['other_taxes_total'],
            'ca_ttc' => $taxBreakdown['ca_ttc'],
            'tax_lines' => $taxBreakdown['by_tax'],
        ];
    }

    /**
     * Sépare CA HT, TVA et autres taxes sur les factures émises de la période.
     *
     * @return array{
     *   ca_ht: float,
     *   tva_total: float,
     *   other_taxes_total: float,
     *   ca_ttc: float,
     *   by_tax: array<int, array{name: string, amount: float}>
     * }
     */
    public function getInvoiceTaxBreakdown(Carbon $start, Carbon $end): array
    {
        $empty = [
            'ca_ht' => 0.0,
            'tva_total' => 0.0,
            'other_taxes_total' => 0.0,
            'ca_ttc' => 0.0,
            'by_tax' => [],
        ];

        $api = $this->invoicingApi();
        if (! $api) {
            return $empty;
        }

        return $api->getTaxBreakdown(
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        );
    }

    /**
     * Rapport explorateur dynamique selon le type demandé.
     *
     * @return array{headers: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    public function getExplorerReport(
        string $reportType,
        Carbon $start,
        Carbon $end,
        ?int $clientId = null,
        int $limit = 100
    ): array {
        $limit = max(1, min(500, $limit));

        return match ($reportType) {
            'ca_client' => $this->explorerClientRevenue($start, $end, $clientId, $limit),
            'top_products' => $this->explorerTopProducts($start, $end, $clientId, $limit, 'revenue'),
            'top_products_qty' => $this->explorerTopProducts($start, $end, $clientId, $limit, 'quantity'),
            'ventes_direct' => $this->explorerDirectSales($start, $end, $clientId, $limit),
            'factures' => $this->explorerInvoices($start, $end, $clientId, $limit),
            'ca_ht_tva' => $this->explorerTaxSummary($start, $end),
            'stock_low' => $this->explorerStockAlert('low', $limit),
            'stock_out' => $this->explorerStockAlert('out', $limit),
            default => [
                'headers' => ['Info'],
                'column_types' => ['text'],
                'rows' => [['Type de rapport inconnu']],
                'title' => 'Rapport',
                'meta' => [],
            ],
        };
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerClientRevenue(Carbon $start, Carbon $end, ?int $clientId, int $limit): array
    {
        $rows = $this->getClientPerformanceReport($start, $end);
        if ($clientId) {
            $rows = array_values(array_filter($rows, fn ($r) => (int) $r['client_id'] === $clientId));
        }
        $rows = array_slice($rows, 0, $limit);

        return [
            'title' => 'Chiffre d\'affaires par client',
            'headers' => ['Client', 'CA Facture', 'CA V. Direct', 'CA total', 'Part %', 'Factures', 'Ventes', 'Devis'],
            'column_types' => ['text', 'money', 'money', 'money_emphasis', 'percent', 'int', 'int', 'int'],
            'rows' => array_map(fn ($r) => [
                $r['client_name'],
                round((float) $r['invoice_revenue'], 2),
                round((float) $r['pos_revenue'], 2),
                round((float) $r['total_revenue'], 2),
                round((float) $r['revenue_share_pct'], 2),
                (int) $r['invoice_count'],
                (int) $r['pos_sale_count'],
                (int) $r['quotation_count'],
            ], $rows),
            'meta' => ['count' => count($rows)],
        ];
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerTopProducts(Carbon $start, Carbon $end, ?int $clientId, int $limit, string $sortBy = 'revenue'): array
    {
        $salesApi = $this->salesApi();
        if (! $salesApi) {
            return [
                'title' => $sortBy === 'quantity' ? 'Marchandises les plus vendues (quantité)' : 'Marchandises les plus vendues (CA)',
                'headers' => ['Référence', 'Article', 'Quantité', 'CA'],
                'column_types' => ['text', 'text', 'qty', 'money_emphasis'],
                'rows' => [],
                'meta' => ['count' => 0],
            ];
        }

        if ($clientId) {
            $products = $salesApi->getTopProductsForClient(
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $clientId,
                $limit,
                $sortBy
            );
        } else {
            $products = $sortBy === 'quantity'
                ? $salesApi->getTopProductsByQuantity($start->format('Y-m-d'), $end->format('Y-m-d'), $limit)
                : $salesApi->getTopProductsByRevenue($start->format('Y-m-d'), $end->format('Y-m-d'), $limit);
        }

        return [
            'title' => $sortBy === 'quantity' ? 'Marchandises les plus vendues (quantité)' : 'Marchandises les plus vendues (CA)',
            'headers' => ['Référence', 'Article', 'Quantité', 'CA'],
            'column_types' => ['text', 'text', 'qty', 'money_emphasis'],
            'rows' => array_map(fn ($r) => [
                $r['item_sku'] ?? '',
                $r['item_name'] ?? '',
                round((float) $r['quantity'], 3),
                round((float) $r['revenue'], 2),
            ], $products),
            'meta' => ['count' => count($products)],
        ];
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerDirectSales(Carbon $start, Carbon $end, ?int $clientId, int $limit): array
    {
        $salesApi = $this->salesApi();
        if (! $salesApi) {
            return ['title' => 'Ventes directes', 'headers' => [], 'column_types' => [], 'rows' => [], 'meta' => []];
        }

        $sales = $salesApi->listSales(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $clientId,
            $limit
        );

        return [
            'title' => 'Ventes directes',
            'headers' => ['N°', 'Date', 'Client', 'Total'],
            'column_types' => ['text', 'date', 'text', 'money_emphasis'],
            'rows' => array_map(fn ($s) => [
                $s['sale_number'],
                $s['sale_date'] ? Carbon::parse($s['sale_date'])->format('d/m/Y') : '',
                $s['client_name'] ?? 'Comptoir',
                round((float) $s['total'], 2),
            ], $sales),
            'meta' => [
                'count' => count($sales),
                'total' => round(array_sum(array_column($sales, 'total')), 2),
            ],
        ];
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerInvoices(Carbon $start, Carbon $end, ?int $clientId, int $limit): array
    {
        $api = $this->invoicingApi();
        if (! $api) {
            return ['title' => 'Factures', 'headers' => [], 'column_types' => [], 'rows' => [], 'meta' => []];
        }

        $invoices = $api->listIssuedInvoices(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $clientId,
            $limit
        );

        return [
            'title' => 'Factures (CA Facture)',
            'headers' => ['N°', 'Date', 'Client', 'Statut', 'HT', 'Taxes', 'TTC', 'Solde'],
            'column_types' => ['text', 'date', 'text', 'badge', 'money', 'money', 'money_emphasis', 'money'],
            'rows' => array_map(fn ($i) => [
                $i['invoice_number'],
                $i['invoice_date'] ? Carbon::parse($i['invoice_date'])->format('d/m/Y') : '',
                $i['client_name'] ?? '',
                self::invoiceStatusLabel((string) $i['status']),
                round(max(0, (float) $i['subtotal'] - (float) $i['discount_amount']), 2),
                round((float) $i['tax_amount'], 2),
                round((float) $i['total'], 2),
                round((float) $i['balance'], 2),
            ], $invoices),
            'meta' => [
                'count' => count($invoices),
                'total_ttc' => round(array_sum(array_column($invoices, 'total')), 2),
                'total_tax' => round(array_sum(array_column($invoices, 'tax_amount')), 2),
            ],
        ];
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerTaxSummary(Carbon $start, Carbon $end): array
    {
        $tax = $this->getInvoiceTaxBreakdown($start, $end);
        $rows = [
            ['CA HT (factures)', $tax['ca_ht']],
            ['Total TVA', $tax['tva_total']],
            ['Autres taxes', $tax['other_taxes_total']],
            ['CA TTC (factures)', $tax['ca_ttc']],
        ];
        foreach ($tax['by_tax'] as $line) {
            $rows[] = ['Détail — ' . $line['name'], $line['amount']];
        }

        return [
            'title' => 'CA HT / TVA',
            'headers' => ['Indicateur', 'Montant'],
            'column_types' => ['text', 'money_emphasis'],
            'rows' => $rows,
            'meta' => $tax,
        ];
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerStockAlert(string $mode, int $limit): array
    {
        $items = $mode === 'out'
            ? $this->getOutOfStockItems()
            : $this->getLowStockItems();
        $items = array_slice($items, 0, $limit);

        $headers = ['Référence', 'Article', 'Stock', 'Seuil'];
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                $item['item_sku'] ?? '',
                $item['item_name'] ?? '',
                $item['available'] ?? '',
                $item['reorder_point'] ?? '',
            ];
        }

        return [
            'title' => $mode === 'out' ? 'Articles en rupture' : 'Articles en stock faible',
            'headers' => $headers,
            'column_types' => ['text', 'text', 'qty', 'qty'],
            'rows' => $rows,
            'meta' => ['count' => count($rows)],
        ];
    }

    public static function quotationStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted' => 'Accepté',
            'suspended' => 'Suspendu',
            'rejected' => 'Rejeté',
            'validated' => 'Accepté',
            default => $status,
        };
    }

    public static function invoiceStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'issued', 'sent' => 'Émise',
            'partial' => 'Part. payée',
            'paid' => 'Payée',
            'overdue' => 'En retard',
            'cancelled' => 'Annulée',
            'superseded' => 'Remplacée',
            default => $status,
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getPreviousDateRange(string $period, Carbon $start, Carbon $end): array
    {
        $days = max(1, $start->diffInDays($end) + 1);
        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return [$prevStart, $prevEnd];
    }

    public function percentChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.0001) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
