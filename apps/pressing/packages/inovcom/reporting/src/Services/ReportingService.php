<?php

namespace InovCom\Reporting\Services;

use App\Services\StoreContextService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportingService
{
    private function applyStoreFilter(Builder $query, string $table): Builder
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        if (
            $storeId
            && Schema::connection('tenant')->hasTable($table)
            && Schema::connection('tenant')->hasColumn($table, 'store_id')
        ) {
            $query->where("{$table}.store_id", $storeId);
        }

        return $query;
    }

    public function hasStoreDimension(): bool
    {
        return Schema::connection('tenant')->hasTable('stores')
            && Schema::connection('tenant')->hasTable('sales')
            && Schema::connection('tenant')->hasColumn('sales', 'store_id');
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
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }

        $gross = (float) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->sum('total');

        if (class_exists(\InovCom\Sales\Services\SaleReturnsService::class)) {
            return max(0, $gross - \InovCom\Sales\Services\SaleReturnsService::totalRefundsBetween(
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            ));
        }

        return $gross;
    }

    public function getSalesCount(Carbon $start, Carbon $end): int
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->count();
    }

    public function getAverageSaleAmount(Carbon $start, Carbon $end): float
    {
        $count = $this->getSalesCount($start, $end);

        return $count > 0 ? $this->getSalesTotal($start, $end) / $count : 0;
    }

    public function getExpensesTotal(Carbon $start, Carbon $end): float
    {
        if (! Schema::connection('tenant')->hasTable('expenses')) {
            return 0;
        }

        return (float) DB::connection('tenant')
            ->table('expenses')
            ->whereBetween('expense_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('status', ['approved', 'paid'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'expenses'))
            ->sum('amount');
    }

    public function getLossesTotal(Carbon $start, Carbon $end): float
    {
        if (! Schema::connection('tenant')->hasTable('loss_records')) {
            return 0;
        }

        return (float) DB::connection('tenant')
            ->table('loss_records')
            ->whereBetween('loss_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->where('status', 'confirmed')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'loss_records'))
            ->sum('value');
    }

    /**
     * Cost of goods sold for the date range.
     * Uses item_unit_prices.cost when unit_id matches, else quantity (in base units) * items.cost.
     */
    public function getCostOfGoodsSold(Carbon $start, Carbon $end): float
    {
        if (! Schema::connection('tenant')->hasTable('sale_lines')
            || ! Schema::connection('tenant')->hasTable('sales')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return 0;
        }

        $hasUnitPrices = Schema::connection('tenant')->hasTable('item_unit_prices');

        if ($hasUnitPrices) {
            $sum = DB::connection('tenant')
                ->table('sale_lines')
                ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
                ->join('items', 'sale_lines.item_id', '=', 'items.id')
                ->leftJoin('item_unit_prices', function ($join) {
                    $join->on('sale_lines.item_id', '=', 'item_unit_prices.item_id')
                        ->on('sale_lines.unit_id', '=', 'item_unit_prices.unit_id');
                })
                ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->selectRaw('COALESCE(SUM(
                    CASE
                        WHEN item_unit_prices.id IS NOT NULL THEN sale_lines.quantity * item_unit_prices.cost
                        ELSE sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * items.cost
                    END
                ), 0) as total')
                ->value('total');
        } else {
            $sum = DB::connection('tenant')
                ->table('sale_lines')
                ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
                ->join('items', 'sale_lines.item_id', '=', 'items.id')
                ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->selectRaw('COALESCE(SUM(
                    sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * items.cost
                ), 0) as total')
                ->value('total');
        }

        return (float) $sum;
    }

    /**
     * Benefit (profit) for the date range: sales margin minus confirmed losses.
     * Expenses and debts are not included in benefit.
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
        if (! Schema::connection('tenant')->hasTable('sale_lines') || ! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->select([
                'sale_lines.item_id',
                DB::raw('MAX(sale_lines.item_name) as item_name'),
                DB::raw('MAX(sale_lines.item_sku) as item_sku'),
                DB::raw('SUM(sale_lines.quantity) as quantity'),
                DB::raw('SUM(sale_lines.line_total) as revenue'),
            ])
            ->groupBy('sale_lines.item_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'item_id' => (int) $r->item_id,
            'item_name' => $r->item_name ?? '-',
            'item_sku' => $r->item_sku,
            'quantity' => (float) $r->quantity,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function getTopSellingByQuantity(Carbon $start, Carbon $end, int $limit = 10): array
    {
        if (! Schema::connection('tenant')->hasTable('sale_lines') || ! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->select([
                'sale_lines.item_id',
                DB::raw('MAX(sale_lines.item_name) as item_name'),
                DB::raw('MAX(sale_lines.item_sku) as item_sku'),
                DB::raw('SUM(sale_lines.quantity) as quantity'),
                DB::raw('SUM(sale_lines.line_total) as revenue'),
            ])
            ->groupBy('sale_lines.item_id')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'item_id' => (int) $r->item_id,
            'item_name' => $r->item_name ?? '-',
            'item_sku' => $r->item_sku,
            'quantity' => (float) $r->quantity,
            'revenue' => (float) $r->revenue,
        ])->all();
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
     * @return array<string, float> [ 'Y-m-d' => total, ... ] — all days in range, 0 when no sales.
     */
    public function getDailySalesTrend(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->select('sale_date')
            ->selectRaw('SUM(total) as total')
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->sale_date] = (float) $r->total;
        }

        $curr = $start->copy();
        while ($curr->lte($end)) {
            $d = $curr->format('Y-m-d');
            if (! isset($out[$d])) {
                $out[$d] = 0.0;
            }
            $curr->addDay();
        }
        ksort($out);

        return $out;
    }

    /**
     * Daily COGS by date for chart (sales - cogs - expenses/days - losses = benefit per day).
     *
     * @return array<string, float> [ 'Y-m-d' => cogs, ... ]
     */
    public function getDailyCogsTrend(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('sale_lines')
            || ! Schema::connection('tenant')->hasTable('sales')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return $this->fillDailyRange($start, $end, 0.0);
        }

        $hasUnitPrices = Schema::connection('tenant')->hasTable('item_unit_prices');
        $dateCol = 'sales.sale_date';

        $query = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->whereBetween($dateCol, [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        $this->applyStoreFilter($query, 'sales');

        if ($hasUnitPrices) {
            $query->leftJoin('item_unit_prices', function ($join) {
                $join->on('sale_lines.item_id', '=', 'item_unit_prices.item_id')
                    ->on('sale_lines.unit_id', '=', 'item_unit_prices.unit_id');
            });
            $rows = $query->select($dateCol)
                ->selectRaw('COALESCE(SUM(
                    CASE
                        WHEN item_unit_prices.id IS NOT NULL THEN sale_lines.quantity * item_unit_prices.cost
                        ELSE sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * items.cost
                    END
                ), 0) as total')
                ->groupBy($dateCol)
                ->orderBy($dateCol)
                ->get();
        } else {
            $rows = $query->select($dateCol)
                ->selectRaw('COALESCE(SUM(
                    sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * items.cost
                ), 0) as total')
                ->groupBy($dateCol)
                ->orderBy($dateCol)
                ->get();
        }

        $out = [];
        foreach ($rows as $r) {
            $out[$r->sale_date] = (float) $r->total;
        }

        return $this->fillDailyRange($start, $end, 0.0, $out);
    }

    /**
     * Daily losses by date.
     *
     * @return array<string, float> [ 'Y-m-d' => value, ... ]
     */
    public function getDailyLossesTrend(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('loss_records')) {
            return $this->fillDailyRange($start, $end, 0.0);
        }

        $rows = DB::connection('tenant')
            ->table('loss_records')
            ->whereBetween('loss_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->where('status', 'confirmed')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'loss_records'))
            ->select('loss_date')
            ->selectRaw('SUM(value) as total')
            ->groupBy('loss_date')
            ->orderBy('loss_date')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->loss_date] = (float) $r->total;
        }

        return $this->fillDailyRange($start, $end, 0.0, $out);
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
        if (! Schema::connection('tenant')->hasTable('expenses') || ! Schema::connection('tenant')->hasTable('expense_categories')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('expenses')
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->whereBetween('expenses.expense_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('expenses.status', ['approved', 'paid'])
            ->select('expense_categories.name as category_name')
            ->selectRaw('SUM(expenses.amount) as total')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'category_name' => $r->category_name,
            'total' => (float) $r->total,
        ])->all();
    }

    /**
     * @return array<int, array{reason_name: string, total_value: float, total_qty: float}>
     */
    public function getLossesByReason(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('loss_records') || ! Schema::connection('tenant')->hasTable('loss_reasons')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('loss_records')
            ->join('loss_reasons', 'loss_records.loss_reason_id', '=', 'loss_reasons.id')
            ->whereBetween('loss_records.loss_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->where('loss_records.status', 'confirmed')
            ->select('loss_reasons.name as reason_name')
            ->selectRaw('SUM(loss_records.value) as total_value')
            ->selectRaw('SUM(loss_records.quantity) as total_qty')
            ->groupBy('loss_reasons.id', 'loss_reasons.name')
            ->orderByDesc('total_value')
            ->get();

        return $rows->map(fn ($r) => [
            'reason_name' => $r->reason_name,
            'total_value' => (float) $r->total_value,
            'total_qty' => (float) $r->total_qty,
        ])->all();
    }

    /**
     * @return array{receivables_total: float, collected_in_period: float, overdue_count: int, overdue_total: float}
     */
    public function getDebtsSummary(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('debts')) {
            return ['receivables_total' => 0, 'collected_in_period' => 0, 'overdue_count' => 0, 'overdue_total' => 0];
        }

        $receivables = (float) DB::connection('tenant')
            ->table('debts')
            ->whereIn('status', ['open', 'partial', 'overdue'])
            ->sum('balance');

        $collected = 0;
        if (Schema::connection('tenant')->hasTable('debt_payments')) {
            $collected = (float) DB::connection('tenant')
                ->table('debt_payments')
                ->whereBetween('payment_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->sum('amount');
        }

        $overdueRows = DB::connection('tenant')
            ->table('debts')
            ->where(function ($q) use ($end) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) use ($end) {
                        $q2->whereIn('status', ['open', 'partial'])
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', $end->format('Y-m-d'));
                    });
            })
            ->get(['balance']);
        $overdueCount = $overdueRows->count();
        $overdueTotal = (float) $overdueRows->sum('balance');

        return [
            'receivables_total' => $receivables,
            'collected_in_period' => $collected,
            'overdue_count' => $overdueCount,
            'overdue_total' => $overdueTotal,
        ];
    }

    public function getPayrollTotal(Carbon $start, Carbon $end): float
    {
        if (! Schema::connection('tenant')->hasTable('payroll_runs')) {
            return 0;
        }

        return (float) DB::connection('tenant')
            ->table('payroll_runs')
            ->whereIn('status', ['processed', 'paid'])
            ->where('period_start', '<=', $end->format('Y-m-d'))
            ->where('period_end', '>=', $start->format('Y-m-d'))
            ->sum('total_gross');
    }

    public function getPurchasesTotal(Carbon $start, Carbon $end): float
    {
        if (! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return 0;
        }

        return (float) DB::connection('tenant')
            ->table('purchase_orders')
            ->whereBetween('order_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->where('status', 'received')
            ->sum('total');
    }

    /**
     * @return array<int, array{item_name: string, item_sku: string|null, available: float, reorder_point: float|null}>
     */
    public function getLowStockItems(): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels') || ! Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('stock_levels')
            ->join('items', 'stock_levels.item_id', '=', 'items.id')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'stock_levels'))
            ->whereNotNull('stock_levels.reorder_point')
            ->whereRaw('stock_levels.available_quantity <= stock_levels.reorder_point')
            ->select('items.name as item_name', 'items.sku as item_sku', 'stock_levels.available_quantity as available', 'stock_levels.reorder_point')
            ->orderBy('stock_levels.available_quantity')
            ->limit(50)
            ->get();

        return $rows->map(fn ($r) => [
            'item_name' => $r->item_name ?? '-',
            'item_sku' => $r->item_sku,
            'available' => (float) $r->available,
            'reorder_point' => $r->reorder_point !== null ? (float) $r->reorder_point : null,
        ])->all();
    }

    /**
     * @return array<int, array{item_name: string, item_sku: string|null, available: float}>
     */
    public function getOutOfStockItems(): array
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels') || ! Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('stock_levels')
            ->join('items', 'stock_levels.item_id', '=', 'items.id')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'stock_levels'))
            ->where('stock_levels.available_quantity', '<=', 0)
            ->select('items.name as item_name', 'items.sku as item_sku', 'stock_levels.available_quantity as available')
            ->orderBy('items.name')
            ->limit(50)
            ->get();

        return $rows->map(fn ($r) => [
            'item_name' => $r->item_name ?? '-',
            'item_sku' => $r->item_sku,
            'available' => (float) $r->available,
        ])->all();
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
        if (! Schema::connection('tenant')->hasTable('clients')) {
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

        if ($this->hasInvoicingModule()) {
            $invoiceRows = DB::connection('tenant')
                ->table('invoices')
                ->join('clients', 'invoices.client_id', '=', 'clients.id')
                ->whereBetween('invoices.invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereNotIn('invoices.status', ['draft', 'cancelled'])
                ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
                ->select('clients.id as client_id', 'clients.name as client_name')
                ->selectRaw('COALESCE(SUM(invoices.total), 0) as invoice_revenue')
                ->selectRaw('COUNT(*) as invoice_count')
                ->groupBy('clients.id', 'clients.name')
                ->get();

            foreach ($invoiceRows as $row) {
                $id = (int) $row->client_id;
                $ensure($id, (string) $row->client_name);
                $byClient[$id]['invoice_revenue'] = (float) $row->invoice_revenue;
                $byClient[$id]['invoice_count'] = (int) $row->invoice_count;
            }
        }

        if (Schema::connection('tenant')->hasTable('sales')) {
            $salesRows = DB::connection('tenant')
                ->table('sales')
                ->join('clients', 'sales.client_id', '=', 'clients.id')
                ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereNotNull('sales.client_id')
                ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
                ->select('clients.id as client_id', 'clients.name as client_name')
                ->selectRaw('COALESCE(SUM(sales.total), 0) as pos_revenue')
                ->selectRaw('COUNT(*) as pos_sale_count')
                ->groupBy('clients.id', 'clients.name')
                ->get();

            foreach ($salesRows as $row) {
                $id = (int) $row->client_id;
                $ensure($id, (string) $row->client_name);
                $byClient[$id]['pos_revenue'] = (float) $row->pos_revenue;
                $byClient[$id]['pos_sale_count'] = (int) $row->pos_sale_count;
            }
        }

        if ($this->hasQuotationsModule()) {
            $quotationRows = DB::connection('tenant')
                ->table('quotations')
                ->join('clients', 'quotations.client_id', '=', 'clients.id')
                ->whereBetween('quotations.quote_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->where('quotations.status', '!=', 'cancelled')
                ->when(true, fn ($q) => $this->applyStoreFilter($q, 'quotations'))
                ->select('clients.id as client_id', 'clients.name as client_name')
                ->selectRaw('COALESCE(SUM(quotations.total), 0) as quotation_total')
                ->selectRaw('COUNT(*) as quotation_count')
                ->groupBy('clients.id', 'clients.name')
                ->get();

            foreach ($quotationRows as $row) {
                $id = (int) $row->client_id;
                $ensure($id, (string) $row->client_name);
                $byClient[$id]['quotation_total'] = (float) $row->quotation_total;
                $byClient[$id]['quotation_count'] = (int) $row->quotation_count;
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
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereNotNull('client_id')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->count(DB::raw('DISTINCT client_id'));
    }

    /**
     * @return array<int, array{store_id:int, store_name:string, sales_count:int, sales_total:float}>
     */
    public function getStorePerformance(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('stores')) {
            return [];
        }

        if (! $this->hasStoreDimension()) {
            $stores = DB::connection('tenant')->table('stores')->orderBy('name')->get(['id', 'name']);

            return $stores->map(fn ($s) => [
                'store_id' => (int) $s->id,
                'store_name' => (string) $s->name,
                'sales_count' => 0,
                'sales_total' => 0.0,
            ])->all();
        }

        $rows = DB::connection('tenant')
            ->table('stores')
            ->leftJoin('sales', function ($join) use ($start, $end) {
                $join->on('stores.id', '=', 'sales.store_id')
                    ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->select(
                'stores.id',
                'stores.name',
                DB::raw('COUNT(sales.id) as sales_count'),
                DB::raw('COALESCE(SUM(sales.total), 0) as sales_total')
            )
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('sales_total')
            ->get();

        return $rows->map(fn ($r) => [
            'store_id' => (int) $r->id,
            'store_name' => (string) $r->name,
            'sales_count' => (int) $r->sales_count,
            'sales_total' => (float) $r->sales_total,
        ])->all();
    }

    /**
     * Top N sales by total amount in the period (biggest tickets).
     *
     * @return array<int, array{sale_date: string, total: float, client_name: string|null}>
     */
    public function getTopSalesByTotal(Carbon $start, Carbon $end, int $limit = 10): array
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $query = DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->select('sales.sale_date', 'sales.total');
        $this->applyStoreFilter($query, 'sales');

        if (Schema::connection('tenant')->hasTable('clients')) {
            $query->leftJoin('clients', 'sales.client_id', '=', 'clients.id')
                ->addSelect('clients.name as client_name');
        } else {
            $query->selectRaw('NULL as client_name');
        }

        $rows = $query->orderByDesc('sales.total')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'sale_date' => $r->sale_date,
            'total' => (float) $r->total,
            'client_name' => $r->client_name ?? null,
        ])->all();
    }

    public function hasQuotationsModule(): bool
    {
        return Schema::connection('tenant')->hasTable('quotations');
    }

    public function hasInvoicingModule(): bool
    {
        return Schema::connection('tenant')->hasTable('invoices');
    }

    public function hasInvoicePaymentsModule(): bool
    {
        return Schema::connection('tenant')->hasTable('invoice_payments');
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
        if (! $this->hasQuotationsModule()) {
            return [
                'count' => 0,
                'total' => 0.0,
                'by_status' => [],
                'accepted_count' => 0,
                'accepted_total' => 0.0,
                'converted_to_invoice' => 0,
            ];
        }

        $rows = DB::connection('tenant')
            ->table('quotations')
            ->whereBetween('quote_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'quotations'))
            ->select('status')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sum')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        $count = 0;
        $total = 0.0;
        foreach ($rows as $r) {
            $status = (string) $r->status;
            $byStatus[$status] = [
                'count' => (int) $r->cnt,
                'total' => (float) $r->total_sum,
            ];
            $count += (int) $r->cnt;
            $total += (float) $r->total_sum;
        }

        $acceptedCount = (int) ($byStatus['accepted']['count'] ?? 0);
        $acceptedTotal = (float) ($byStatus['accepted']['total'] ?? 0);

        $converted = 0;
        if ($this->hasInvoicingModule()) {
            $converted = (int) DB::connection('tenant')
                ->table('invoices')
                ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereNotNull('quotation_id')
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
                ->count(DB::raw('DISTINCT quotation_id'));
        }

        return [
            'count' => $count,
            'total' => $total,
            'by_status' => $byStatus,
            'accepted_count' => $acceptedCount,
            'accepted_total' => $acceptedTotal,
            'converted_to_invoice' => $converted,
        ];
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
        if (! $this->hasInvoicingModule()) {
            return [
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
        }

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->select('status')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sum')
            ->selectRaw('COALESCE(SUM(balance), 0) as balance_sum')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[(string) $r->status] = [
                'count' => (int) $r->cnt,
                'total' => (float) $r->total_sum,
            ];
        }

        $issuedStatuses = ['issued', 'partial', 'paid'];
        $issuedCount = 0;
        $issuedTotal = 0.0;
        foreach ($issuedStatuses as $st) {
            $issuedCount += (int) ($byStatus[$st]['count'] ?? 0);
            $issuedTotal += (float) ($byStatus[$st]['total'] ?? 0);
        }

        $outstanding = (float) DB::connection('tenant')
            ->table('invoices')
            ->whereIn('status', ['issued', 'partial'])
            ->where('balance', '>', 0.01)
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->sum('balance');

        $partialBalance = (float) DB::connection('tenant')
            ->table('invoices')
            ->where('status', 'partial')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->sum('balance');

        return [
            'issued_count' => $issuedCount,
            'issued_total' => $issuedTotal,
            'draft_count' => (int) ($byStatus['draft']['count'] ?? 0),
            'paid_count' => (int) ($byStatus['paid']['count'] ?? 0),
            'paid_total' => (float) ($byStatus['paid']['total'] ?? 0),
            'partial_count' => (int) ($byStatus['partial']['count'] ?? 0),
            'partial_balance' => $partialBalance,
            'outstanding_balance' => $outstanding,
            'cancelled_count' => (int) ($byStatus['cancelled']['count'] ?? 0),
            'by_status' => $byStatus,
        ];
    }

    public function getInvoicePaymentsTotal(Carbon $start, Carbon $end): float
    {
        if (! $this->hasInvoicePaymentsModule()) {
            return 0.0;
        }

        return (float) DB::connection('tenant')
            ->table('invoice_payments')
            ->whereBetween('payment_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->sum('amount');
    }

    /**
     * @return array<int, array{method: string, method_label: string, total: float, count: int}>
     */
    public function getInvoicePaymentsByMethod(Carbon $start, Carbon $end): array
    {
        if (! $this->hasInvoicePaymentsModule()) {
            return [];
        }

        $labels = [
            'cash' => 'Espèces',
            'check' => 'Chèque',
            'bank_transfer' => 'Virement',
            'mobile_money' => 'Mobile Money',
            'other' => 'Autre',
        ];

        $rows = DB::connection('tenant')
            ->table('invoice_payments')
            ->whereBetween('payment_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->select('payment_method')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => [
            'method' => (string) $r->payment_method,
            'method_label' => $labels[$r->payment_method] ?? $r->payment_method,
            'total' => (float) $r->total,
            'count' => (int) $r->cnt,
        ])->all();
    }

    /**
     * @return array{confirmed_count: int, draft_count: int}
     */
    public function getDeliveriesSummary(Carbon $start, Carbon $end): array
    {
        if (! Schema::connection('tenant')->hasTable('delivery_notes')) {
            return ['confirmed_count' => 0, 'draft_count' => 0];
        }

        $confirmed = (int) DB::connection('tenant')
            ->table('delivery_notes')
            ->where('status', 'confirmed')
            ->whereBetween('delivery_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'delivery_notes'))
            ->count();

        $draft = (int) DB::connection('tenant')
            ->table('delivery_notes')
            ->where('status', 'draft')
            ->whereBetween('delivery_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'delivery_notes'))
            ->count();

        return ['confirmed_count' => $confirmed, 'draft_count' => $draft];
    }

    /**
     * @return array<int, array{invoice_number: string, client_name: string, balance: float, due_date: string|null, status: string}>
     */
    public function getTopOutstandingInvoices(int $limit = 10): array
    {
        if (! $this->hasInvoicingModule() || ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->join('clients', 'invoices.client_id', '=', 'clients.id')
            ->whereIn('invoices.status', ['issued', 'partial'])
            ->where('invoices.balance', '>', 0.01)
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->select(
                'invoices.invoice_number',
                'clients.name as client_name',
                'invoices.balance',
                'invoices.due_date',
                'invoices.status'
            )
            ->orderByDesc('invoices.balance')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'invoice_number' => $r->invoice_number,
            'client_name' => $r->client_name,
            'balance' => (float) $r->balance,
            'due_date' => $r->due_date,
            'status' => $r->status,
        ])->all();
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

        if (! $this->hasInvoicingModule()) {
            return $empty;
        }

        $issuedStatuses = ['issued', 'partial', 'paid'];

        $invoiceAgg = DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('status', $issuedStatuses)
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(subtotal, 0) - COALESCE(discount_amount, 0), 0)), 0) as ca_ht')
            ->selectRaw('COALESCE(SUM(total), 0) as ca_ttc')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_fallback')
            ->first();

        $caHt = (float) ($invoiceAgg->ca_ht ?? 0);
        $caTtc = (float) ($invoiceAgg->ca_ttc ?? 0);
        $taxFallback = (float) ($invoiceAgg->tax_fallback ?? 0);

        $tvaTotal = 0.0;
        $otherTaxes = 0.0;
        $byTax = [];

        if (Schema::connection('tenant')->hasTable('invoice_tax_lines')) {
            $taxQuery = DB::connection('tenant')
                ->table('invoice_tax_lines as tl')
                ->join('invoices as i', 'i.id', '=', 'tl.invoice_id')
                ->whereBetween('i.invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->whereIn('i.status', $issuedStatuses)
                ->when(true, function ($q) {
                    $storeId = app(StoreContextService::class)->currentStoreId();
                    if (
                        $storeId
                        && Schema::connection('tenant')->hasColumn('invoices', 'store_id')
                    ) {
                        $q->where('i.store_id', $storeId);
                    }

                    return $q;
                });

            if (Schema::connection('tenant')->hasColumn('invoice_tax_lines', 'tax_effect')) {
                $taxQuery->where(function ($q) {
                    $q->whereNull('tl.tax_effect')
                        ->orWhere('tl.tax_effect', '<>', 'subtract');
                });
            }

            $taxRows = $taxQuery
                ->select('tl.tax_name')
                ->selectRaw('COALESCE(SUM(tl.tax_amount), 0) as amount')
                ->groupBy('tl.tax_name')
                ->get();

            foreach ($taxRows as $row) {
                $amount = (float) $row->amount;
                if ($amount <= 0) {
                    continue;
                }
                $name = (string) $row->tax_name;
                $byTax[] = ['name' => $name, 'amount' => $amount];
                if (str_contains(mb_strtoupper($name), 'TVA')) {
                    $tvaTotal += $amount;
                } else {
                    $otherTaxes += $amount;
                }
            }
        }

        if ($tvaTotal <= 0 && $otherTaxes <= 0 && $taxFallback > 0) {
            $tvaTotal = $taxFallback;
            $byTax[] = ['name' => 'TVA / taxes', 'amount' => $taxFallback];
        }

        return [
            'ca_ht' => round($caHt, 2),
            'tva_total' => round($tvaTotal, 2),
            'other_taxes_total' => round($otherTaxes, 2),
            'ca_ttc' => round($caTtc, 2),
            'by_tax' => $byTax,
        ];
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
        $orderCol = $sortBy === 'quantity' ? 'quantity' : 'revenue';

        if ($clientId && Schema::connection('tenant')->hasTable('sale_lines')) {
            $q = DB::connection('tenant')
                ->table('sale_lines')
                ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
                ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->where('sales.client_id', $clientId)
                ->when(true, fn ($query) => $this->applyStoreFilter($query, 'sales'))
                ->select('sale_lines.item_id', 'sale_lines.item_name', 'sale_lines.item_sku')
                ->selectRaw('SUM(sale_lines.quantity) as quantity')
                ->selectRaw('SUM(sale_lines.line_total) as revenue')
                ->groupBy('sale_lines.item_id', 'sale_lines.item_name', 'sale_lines.item_sku')
                ->orderByDesc($orderCol)
                ->limit($limit)
                ->get();

            $products = $q->map(fn ($r) => [
                'item_name' => $r->item_name,
                'item_sku' => $r->item_sku,
                'quantity' => (float) $r->quantity,
                'revenue' => (float) $r->revenue,
            ])->all();
        } else {
            $products = $sortBy === 'quantity'
                ? $this->getTopSellingByQuantity($start, $end, $limit)
                : $this->getTopSellingProducts($start, $end, $limit);
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
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return ['title' => 'Ventes directes', 'headers' => [], 'column_types' => [], 'rows' => [], 'meta' => []];
        }

        $sales = DB::connection('tenant')
            ->table('sales')
            ->leftJoin('clients', 'clients.id', '=', 'sales.client_id')
            ->whereBetween('sales.sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($clientId, fn ($q) => $q->where('sales.client_id', $clientId))
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->orderByDesc('sales.sale_date')
            ->orderByDesc('sales.id')
            ->limit($limit)
            ->get([
                'sales.sale_number',
                'sales.sale_date',
                'sales.total',
                'clients.name as client_name',
            ]);

        return [
            'title' => 'Ventes directes',
            'headers' => ['N°', 'Date', 'Client', 'Total'],
            'column_types' => ['text', 'date', 'text', 'money_emphasis'],
            'rows' => $sales->map(fn ($s) => [
                $s->sale_number,
                Carbon::parse($s->sale_date)->format('d/m/Y'),
                $s->client_name ?? 'Comptoir',
                round((float) $s->total, 2),
            ])->all(),
            'meta' => ['count' => $sales->count(), 'total' => round($sales->sum('total'), 2)],
        ];
    }

    /**
     * @return array{headers: list<string>, column_types: list<string>, rows: list<list<string|float|int|null>>, title: string, meta: array<string, mixed>}
     */
    private function explorerInvoices(Carbon $start, Carbon $end, ?int $clientId, int $limit): array
    {
        if (! $this->hasInvoicingModule()) {
            return ['title' => 'Factures', 'headers' => [], 'column_types' => [], 'rows' => [], 'meta' => []];
        }

        $invoices = DB::connection('tenant')
            ->table('invoices')
            ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereBetween('invoices.invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereNotIn('invoices.status', ['draft', 'cancelled'])
            ->when($clientId, fn ($q) => $q->where('invoices.client_id', $clientId))
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->orderByDesc('invoices.invoice_date')
            ->limit($limit)
            ->get([
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.status',
                'invoices.subtotal',
                'invoices.discount_amount',
                'invoices.tax_amount',
                'invoices.total',
                'invoices.balance',
                'clients.name as client_name',
            ]);

        return [
            'title' => 'Factures (CA Facture)',
            'headers' => ['N°', 'Date', 'Client', 'Statut', 'HT', 'Taxes', 'TTC', 'Solde'],
            'column_types' => ['text', 'date', 'text', 'badge', 'money', 'money', 'money_emphasis', 'money'],
            'rows' => $invoices->map(fn ($i) => [
                $i->invoice_number,
                Carbon::parse($i->invoice_date)->format('d/m/Y'),
                $i->client_name ?? '',
                self::invoiceStatusLabel((string) $i->status),
                round(max(0, (float) $i->subtotal - (float) $i->discount_amount), 2),
                round((float) $i->tax_amount, 2),
                round((float) $i->total, 2),
                round((float) $i->balance, 2),
            ])->all(),
            'meta' => [
                'count' => $invoices->count(),
                'total_ttc' => round($invoices->sum('total'), 2),
                'total_tax' => round($invoices->sum('tax_amount'), 2),
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
            'issued' => 'Émise',
            'partial' => 'Part. payée',
            'paid' => 'Payée',
            'cancelled' => 'Annulée',
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
