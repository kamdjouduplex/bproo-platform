<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    private function applyStoreFilter(Builder $query, string $table = ''): Builder
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        $column = $table !== '' ? "{$table}.store_id" : 'store_id';
        $checkTable = $table !== '' ? $table : $query->from;
        $checkTable = str_contains((string) $checkTable, ' ') ? explode(' ', (string) $checkTable)[0] : (string) $checkTable;

        if ($storeId && Schema::connection('tenant')->hasTable($checkTable) && Schema::connection('tenant')->hasColumn($checkTable, 'store_id')) {
            $query->where($column, $storeId);
        }

        return $query;
    }

    public function hasStoreDimension(): bool
    {
        return Schema::connection('tenant')->hasTable('stores')
            && Schema::connection('tenant')->hasTable('sales')
            && Schema::connection('tenant')->hasColumn('sales', 'store_id');
    }

    public function salesToday(): float
    {
        if ($this->shouldUseInvoiceRevenue()) {
            return $this->invoiceRevenueBetween(now()->startOfDay(), now()->endOfDay());
        }

        return $this->salesBetween(now()->startOfDay(), now()->endOfDay());
    }

    public function salesMonth(): float
    {
        if ($this->shouldUseInvoiceRevenue()) {
            return $this->invoiceRevenueBetween(now()->startOfMonth(), now()->endOfMonth());
        }

        return $this->salesBetween(now()->startOfMonth(), now()->endOfMonth());
    }

    public function salesCountToday(): int
    {
        if ($this->shouldUseInvoiceRevenue()) {
            return $this->invoiceCountBetween(now()->startOfDay(), now()->endOfDay());
        }

        return $this->salesCountBetween(now()->startOfDay(), now()->endOfDay());
    }

    public function salesCountMonth(): int
    {
        if ($this->shouldUseInvoiceRevenue()) {
            return $this->invoiceCountBetween(now()->startOfMonth(), now()->endOfMonth());
        }

        return $this->salesCountBetween(now()->startOfMonth(), now()->endOfMonth());
    }

    public function expensesToday(): float
    {
        if (!Schema::connection('tenant')->hasTable('expenses')) {
            return 0;
        }
        $today = now()->format('Y-m-d');
        return (float) DB::connection('tenant')
            ->table('expenses')
            ->where('expense_date', $today)
            ->whereIn('status', ['approved', 'paid'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'expenses'))
            ->sum('amount');
    }

    public function expensesMonth(): float
    {
        if (!Schema::connection('tenant')->hasTable('expenses')) {
            return 0;
        }
        return (float) DB::connection('tenant')
            ->table('expenses')
            ->whereBetween('expense_date', [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')])
            ->whereIn('status', ['approved', 'paid'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'expenses'))
            ->sum('amount');
    }

    public function lossesToday(): float
    {
        if (!Schema::connection('tenant')->hasTable('loss_records')) {
            return 0;
        }
        $today = now()->format('Y-m-d');
        return (float) DB::connection('tenant')
            ->table('loss_records')
            ->where('loss_date', $today)
            ->where('status', 'confirmed')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'loss_records'))
            ->sum('value');
    }

    public function lossesMonth(): float
    {
        if (!Schema::connection('tenant')->hasTable('loss_records')) {
            return 0;
        }
        return (float) DB::connection('tenant')
            ->table('loss_records')
            ->whereBetween('loss_date', [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')])
            ->where('status', 'confirmed')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'loss_records'))
            ->sum('value');
    }

    /**
     * Cost of goods sold for a date range (sum of quantity * item cost for all sale lines).
     */
    private function costOfGoodsSoldBetween(Carbon $start, Carbon $end): float
    {
        if (!Schema::connection('tenant')->hasTable('sale_lines')
            || !Schema::connection('tenant')->hasTable('sales')
            || !Schema::connection('tenant')->hasTable('items')) {
            return 0;
        }
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');
        $sum = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->whereBetween('sales.sale_date', [$startStr, $endStr])
            ->selectRaw('COALESCE(SUM(sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * items.cost), 0) as total')
            ->value('total');
        return (float) $sum;
    }

    public function costOfGoodsSoldToday(): float
    {
        return $this->costOfGoodsSoldBetween(now()->startOfDay(), now()->endOfDay());
    }

    /**
     * Cost of goods sold for the current month.
     */
    public function costOfGoodsSoldMonth(): float
    {
        return $this->costOfGoodsSoldBetween(now()->startOfMonth(), now()->endOfMonth());
    }

    /**
     * Benefit (profit) for today: sales margin minus confirmed losses.
     * Expenses and debts are not included in benefit.
     */
    public function benefitToday(): float
    {
        return $this->salesToday()
            - $this->costOfGoodsSoldToday()
            - $this->lossesToday();
    }

    /**
     * Benefit (profit) for the current month: sales margin minus confirmed losses.
     * Expenses and debts are not included in benefit.
     */
    public function benefitMonth(): float
    {
        return $this->salesMonth()
            - $this->costOfGoodsSoldMonth()
            - $this->lossesMonth();
    }

    /**
     * @return array<int, array{id: int, sale_number: string, sale_date: string, total: float}>
     */
    public function recentSales(int $limit = 8): array
    {
        if (!Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }
        $rows = DB::connection('tenant')
            ->table('sales')
            ->orderByDesc('sale_date')
            ->orderByDesc('created_at')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->limit($limit)
            ->get(['id', 'sale_number', 'sale_date', 'total']);

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'sale_number' => $r->sale_number,
            'sale_date' => $r->sale_date,
            'total' => (float) $r->total,
        ])->all();
    }

    /**
     * @return array<int, array{id: int, invoice_number: string, invoice_date: string, client_name: string|null, total: float, balance: float, status: string}>
     */
    public function recentInvoices(int $limit = 8): array
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return [];
        }

        $hasClients = Schema::connection('tenant')->hasTable('clients');
        $hasBalance = Schema::connection('tenant')->hasColumn('invoices', 'balance');

        $query = DB::connection('tenant')
            ->table('invoices')
            ->when($hasClients, fn ($q) => $q->leftJoin('clients', 'invoices.client_id', '=', 'clients.id'))
            ->whereNotIn('invoices.status', ['cancelled'])
            ->orderByDesc('invoices.invoice_date')
            ->orderByDesc('invoices.id')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->limit($limit);

        $columns = [
            'invoices.id',
            'invoices.invoice_number',
            'invoices.invoice_date',
            'invoices.total',
            'invoices.status',
        ];
        if ($hasBalance) {
            $columns[] = 'invoices.balance';
        }
        if ($hasClients) {
            $columns[] = 'clients.name as client_name';
        }

        $rows = $query->get($columns);

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'invoice_number' => (string) $r->invoice_number,
            'invoice_date' => (string) $r->invoice_date,
            'client_name' => $r->client_name ?? null,
            'total' => (float) $r->total,
            'balance' => $hasBalance ? (float) ($r->balance ?? 0) : 0.0,
            'status' => (string) $r->status,
        ])->all();
    }

    /**
     * @return array<int, array{item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    public function topProductsMonth(int $limit = 5): array
    {
        if (!Schema::connection('tenant')->hasTable('sale_lines') || !Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }
        $start = now()->startOfMonth()->format('Y-m-d');
        $end = now()->endOfMonth()->format('Y-m-d');
        $rows = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->select([
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
            'item_name' => $r->item_name ?? '-',
            'item_sku' => $r->item_sku,
            'quantity' => (float) $r->quantity,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    /**
     * @return array<int, array{store_id:int, store_name:string, sales_total:float, sales_count:int}>
     */
    public function storePerformanceMonth(): array
    {
        if (!Schema::connection('tenant')->hasTable('stores')) {
            return [];
        }

        $stores = DB::connection('tenant')
            ->table('stores')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($stores->isEmpty()) {
            return [];
        }

        if (!$this->hasStoreDimension()) {
            return $stores->map(fn ($s) => [
                'store_id' => (int) $s->id,
                'store_name' => (string) $s->name,
                'sales_total' => 0.0,
                'sales_count' => 0,
            ])->all();
        }

        $start = now()->startOfMonth()->format('Y-m-d');
        $end = now()->endOfMonth()->format('Y-m-d');

        $rows = DB::connection('tenant')
            ->table('stores')
            ->leftJoin('sales', function ($join) use ($start, $end) {
                $join->on('stores.id', '=', 'sales.store_id')
                    ->whereBetween('sales.sale_date', [$start, $end]);
            })
            ->select(
                'stores.id',
                'stores.name',
                DB::raw('COALESCE(SUM(sales.total), 0) as sales_total'),
                DB::raw('COUNT(sales.id) as sales_count')
            )
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('sales_total')
            ->get();

        return $rows->map(fn ($r) => [
            'store_id' => (int) $r->id,
            'store_name' => (string) $r->name,
            'sales_total' => (float) $r->sales_total,
            'sales_count' => (int) $r->sales_count,
        ])->all();
    }

    private function salesBetween(Carbon $start, Carbon $end): float
    {
        if ($this->shouldUseInvoiceRevenue()) {
            return $this->invoiceRevenueBetween($start, $end);
        }

        if (!Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }
        $gross = (float) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->sum('total');

        if (class_exists(\InovCom\Sales\Services\SaleReturnsService::class)) {
            $returns = \InovCom\Sales\Services\SaleReturnsService::totalRefundsBetween(
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            );

            return max(0, $gross - $returns);
        }

        return $gross;
    }

    private function salesCountBetween(Carbon $start, Carbon $end): int
    {
        if ($this->shouldUseInvoiceRevenue()) {
            return $this->invoiceCountBetween($start, $end);
        }

        if (!Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }
        return (int) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->count();
    }

    /**
     * Variation du CA du jour vs hier (%). Null si pas de base de comparaison.
     */
    public function salesTrendVsYesterday(): ?float
    {
        $today = $this->salesToday();
        $yesterday = $this->salesBetween(
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay()
        );

        if ($yesterday <= 0) {
            return $today > 0 ? 100.0 : null;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 1);
    }

    /**
     * @return array<int, array{date: string, label: string, total: float, count: int, bar_pct: float}>
     */
    public function salesLast7Days(): array
    {
        $days = [];
        $max = 0.0;

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $total = $this->salesBetween($day->copy()->startOfDay(), $day->copy()->endOfDay());
            $count = $this->salesCountBetween($day->copy()->startOfDay(), $day->copy()->endOfDay());
            $max = max($max, $total);
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->translatedFormat('D'),
                'short_label' => $day->format('d/m'),
                'total' => $total,
                'count' => $count,
                'is_today' => $day->isToday(),
            ];
        }

        foreach ($days as &$day) {
            $day['bar_pct'] = $max > 0 ? round(($day['total'] / $max) * 100, 1) : 0.0;
        }
        unset($day);

        return $days;
    }

    /**
     * @return array<int, array{item_id: int, name: string, sku: string|null, available: float, reorder_point: float}>
     */
    public function lowStockItems(int $limit = 6): array
    {
        if (!Schema::connection('tenant')->hasTable('stock_levels')
            || !Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $storeId = app(StoreContextService::class)->currentStoreId();
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('stock_levels', 'store_id');

        $query = DB::connection('tenant')
            ->table('items')
            ->join('stock_levels', 'items.id', '=', 'stock_levels.item_id')
            ->whereNotNull('stock_levels.reorder_point')
            ->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= stock_levels.reorder_point')
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('stock_levels.store_id', $storeId))
            ->orderBy('stock_levels.available_quantity')
            ->limit($limit)
            ->get([
                'items.id as item_id',
                'items.name',
                'items.sku',
                'stock_levels.available_quantity',
                'stock_levels.reorder_point',
            ]);

        return $query->map(fn ($r) => [
            'item_id' => (int) $r->item_id,
            'name' => (string) $r->name,
            'sku' => $r->sku,
            'available' => (float) $r->available_quantity,
            'reorder_point' => (float) $r->reorder_point,
        ])->all();
    }

    /**
     * @return array{is_open: bool, session_number: string|null, balance: float}|null
     */
    public function caisseSnapshot(): ?array
    {
        if (!Schema::connection('tenant')->hasTable('caisse_sessions')
            || !class_exists(\InovCom\Caisse\Services\CaisseService::class)) {
            return null;
        }

        $service = app(\InovCom\Caisse\Services\CaisseService::class);
        if (!$service->isReady()) {
            return null;
        }

        $session = $service->activeSession();

        return [
            'is_open' => $session !== null,
            'session_number' => $session?->session_number,
            'balance' => $service->currentBalance(),
        ];
    }

    public function invoiceCollectedMonth(): float
    {
        return $this->invoiceCollectedBetween(now()->startOfMonth(), now()->endOfMonth());
    }

    public function invoiceCollectedBetween(Carbon $start, Carbon $end): float
    {
        if (!Schema::connection('tenant')->hasTable('invoice_payments')
            || !Schema::connection('tenant')->hasTable('invoices')) {
            return 0.0;
        }

        $query = DB::connection('tenant')
            ->table('invoice_payments')
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoice_payments.payment_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if (Schema::connection('tenant')->hasColumn('invoice_payments', 'status')) {
            $query->where(function ($q) {
                $q->whereNull('invoice_payments.status')
                    ->orWhere('invoice_payments.status', '!=', 'cancelled');
            });
        }

        $query->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'));

        return (float) $query->selectRaw(
            'COALESCE(SUM(
                CASE
                    WHEN invoices.total > 0
                    THEN invoice_payments.amount * GREATEST(0, invoices.subtotal - invoices.discount_amount) / invoices.total
                    ELSE 0
                END
            ), 0) as collected_ht'
        )->value('collected_ht');
    }

    public function pendingInvoicesCount(): int
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('invoices')
            ->whereIn('status', ['issued', 'partial'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->count();
    }

    public function unpaidInvoicesTotal(): float
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return 0.0;
        }

        $hasBalance = Schema::connection('tenant')->hasColumn('invoices', 'balance');
        $paidColumn = Schema::connection('tenant')->hasColumn('invoices', 'amount_paid') ? 'amount_paid' : null;

        $rows = DB::connection('tenant')
            ->table('invoices')
            ->whereIn('status', ['issued', 'partial'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->get(array_filter(['total', $hasBalance ? 'balance' : null, $paidColumn]));

        return (float) $rows->sum(function ($r) use ($hasBalance, $paidColumn) {
            if ($hasBalance && isset($r->balance)) {
                return max(0, (float) $r->balance);
            }

            return max(0, (float) $r->total - (float) ($paidColumn ? ($r->{$paidColumn} ?? 0) : 0));
        });
    }

    private function shouldUseInvoiceRevenue(): bool
    {
        return Schema::connection('tenant')->hasTable('invoices');
    }

    /**
     * CA facturation = montant HT net (TTC − taxes reversées).
     */
    public function invoiceRevenueBetween(Carbon $start, Carbon $end): float
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->selectRaw('COALESCE(SUM(GREATEST(0, subtotal - discount_amount)), 0) as revenue')
            ->value('revenue');
    }

    public function invoiceCountBetween(Carbon $start, Carbon $end): int
    {
        if (!Schema::connection('tenant')->hasTable('invoices')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('invoices')
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('status', ['issued', 'partial', 'paid'])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'invoices'))
            ->count();
    }

    /**
     * POS-only revenue (ignores invoicing tables) — used by pharmacy dashboard.
     */
    public function posSalesToday(): float
    {
        return $this->posSalesBetween(now()->startOfDay(), now()->endOfDay());
    }

    public function posSalesMonth(): float
    {
        return $this->posSalesBetween(now()->startOfMonth(), now()->endOfMonth());
    }

    public function posSalesCountToday(): int
    {
        return $this->posSalesCountBetween(now()->startOfDay(), now()->endOfDay());
    }

    public function posTrendVsYesterday(): ?float
    {
        $today = $this->posSalesToday();
        $yesterday = $this->posSalesBetween(
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay()
        );

        if ($yesterday <= 0) {
            return $today > 0 ? 100.0 : null;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 1);
    }

    /**
     * @return array<int, array{date: string, label: string, short_label: string, total: float, count: int, is_today: bool, bar_pct: float}>
     */
    public function posSalesLast7Days(): array
    {
        $days = [];
        $max = 0.0;

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $total = $this->posSalesBetween($day->copy()->startOfDay(), $day->copy()->endOfDay());
            $count = $this->posSalesCountBetween($day->copy()->startOfDay(), $day->copy()->endOfDay());
            $max = max($max, $total);
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->translatedFormat('D'),
                'short_label' => $day->format('d/m'),
                'total' => $total,
                'count' => $count,
                'is_today' => $day->isToday(),
            ];
        }

        foreach ($days as &$day) {
            $day['bar_pct'] = $max > 0 ? round(($day['total'] / $max) * 100, 1) : 0.0;
        }
        unset($day);

        return $days;
    }

    /**
     * Distinct catalog items with available stock > 0.
     */
    public function itemsInStockCount(): int
    {
        if (! Schema::connection('tenant')->hasTable('stock_levels')) {
            return 0;
        }

        $storeId = app(StoreContextService::class)->currentStoreId();
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('stock_levels', 'store_id');

        return (int) DB::connection('tenant')
            ->table('stock_levels')
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('store_id', $storeId))
            ->where('available_quantity', '>', 0)
            ->selectRaw('COUNT(DISTINCT item_id) as aggregate_count')
            ->value('aggregate_count');
    }

    public function expiringBatchesCount(int $withinDays = 90): int
    {
        if (! Schema::connection('tenant')->hasTable('batches')) {
            return 0;
        }

        $today = now()->toDateString();
        $until = now()->addDays($withinDays)->toDateString();

        return (int) DB::connection('tenant')
            ->table('batches')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $until)
            ->count();
    }

    public function expiredBatchesCount(): int
    {
        if (! Schema::connection('tenant')->hasTable('batches')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('batches')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->count();
    }

    /**
     * @return array<int, array{batch_id: int, item_id: int, item_name: string, batch_number: string, expiry_date: string, quantity: float, days_left: int}>
     */
    public function expiringBatches(int $withinDays = 90, int $limit = 8): array
    {
        if (! Schema::connection('tenant')->hasTable('batches')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return [];
        }

        $today = now()->startOfDay();
        $until = now()->addDays($withinDays)->toDateString();

        $rows = DB::connection('tenant')
            ->table('batches')
            ->join('items', 'batches.item_id', '=', 'items.id')
            ->where('batches.quantity', '>', 0)
            ->whereDate('batches.expiry_date', '>=', $today->toDateString())
            ->whereDate('batches.expiry_date', '<=', $until)
            ->orderBy('batches.expiry_date')
            ->limit($limit)
            ->get([
                'batches.id as batch_id',
                'batches.item_id',
                'items.name as item_name',
                'batches.batch_number',
                'batches.expiry_date',
                'batches.quantity',
            ]);

        return $rows->map(function ($r) use ($today) {
            $expiry = Carbon::parse($r->expiry_date)->startOfDay();

            return [
                'batch_id' => (int) $r->batch_id,
                'item_id' => (int) $r->item_id,
                'item_name' => (string) $r->item_name,
                'batch_number' => (string) $r->batch_number,
                'expiry_date' => $expiry->format('Y-m-d'),
                'quantity' => (float) $r->quantity,
                'days_left' => (int) $today->diffInDays($expiry, false),
            ];
        })->all();
    }

    public function activePrescriptionsCount(): int
    {
        if (! Schema::connection('tenant')->hasTable('prescriptions')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('prescriptions')
            ->where('status', 'active')
            ->count();
    }

    private function posSalesBetween(Carbon $start, Carbon $end): float
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0.0;
        }

        $gross = (float) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->sum('total');

        if (class_exists(\InovCom\Sales\Services\SaleReturnsService::class)) {
            $returns = \InovCom\Sales\Services\SaleReturnsService::totalRefundsBetween(
                $start->format('Y-m-d'),
                $end->format('Y-m-d')
            );

            return max(0, $gross - $returns);
        }

        return $gross;
    }

    private function posSalesCountBetween(Carbon $start, Carbon $end): int
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
}
