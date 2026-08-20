<?php

namespace Pharma\Services;

use App\Services\StoreContextService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Sales\Models\Payment;
use InovCom\Sales\Services\SaleReturnsService;

class PharmaReportingService
{
    public function presetBounds(string $period): array
    {
        $today = now();

        return match ($period) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'last_7_days' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(string $period, string $dateFrom = '', string $dateTo = ''): array
    {
        if ($period === 'custom' && $dateFrom !== '' && $dateTo !== '') {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $to = Carbon::parse($dateTo)->endOfDay();
            if ($to->lt($from)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [$from, $to];
        }

        return $this->presetBounds($period);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousRange(Carbon $from, Carbon $to): array
    {
        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        return [$prevFrom, $prevTo];
    }

    public function periodLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->translatedFormat('d F Y');
        }

        return $from->format('d/m/Y').' — '.$to->format('d/m/Y');
    }

    public function trendPct(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : ($current < 0 ? -100.0 : null);
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    public function trendPts(float $current, float $previous): ?float
    {
        return round($current - $previous, 1);
    }

    /**
     * @return array{
     *   ca: float, sales_count: int, qty_sold: float, distinct_products: int,
     *   avg_basket: float, cogs: float, margin: float, margin_rate: float,
     *   expenses: float, losses: float, purchases: float, benefit: float,
     *   stock_value: float, overdue_total: float
     * }
     */
    public function kpis(Carbon $from, Carbon $to): array
    {
        $ca = $this->netSales($from, $to);
        $count = $this->salesCount($from, $to);
        $qty = $this->qtySold($from, $to);
        $distinct = $this->distinctProducts($from, $to);
        $cogs = $this->cogs($from, $to);
        $margin = $ca - $cogs;
        $losses = $this->losses($from, $to);
        $expenses = $this->expenses($from, $to);
        $purchases = $this->purchasesReceived($from, $to);

        return [
            'ca' => $ca,
            'sales_count' => $count,
            'qty_sold' => $qty,
            'distinct_products' => $distinct,
            'avg_basket' => $count > 0 ? $ca / $count : 0.0,
            'cogs' => $cogs,
            'margin' => $margin,
            'margin_rate' => $ca > 0 ? round($margin / $ca * 100, 1) : 0.0,
            'expenses' => $expenses,
            'losses' => $losses,
            'purchases' => $purchases,
            'benefit' => $margin - $losses,
            'stock_value' => $this->stockValue(),
            'overdue_total' => $this->overdueDebtsTotal(),
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, short: string, total: float, is_today: bool}>
     */
    public function dailyCa(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        if ($from->diffInDays($to) > 92) {
            return $this->monthlyCa($from, $to);
        }

        $map = [];
        if ($this->hasTable('sales')) {
            $rows = DB::connection('tenant')->table('sales')
                ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
                ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
                ->select('sale_date')
                ->selectRaw('SUM(total) as total')
                ->groupBy('sale_date')
                ->pluck('total', 'sale_date');
            foreach ($rows as $date => $total) {
                $map[(string) $date] = (float) $total;
            }
        }

        $refunds = [];
        if ($this->hasTable('sale_returns') && $this->hasColumn('sale_returns', 'total_refund')) {
            $rows = DB::connection('tenant')->table('sale_returns')
                ->where('status', 'confirmed')
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->when(
                    $this->hasColumn('sale_returns', 'store_id'),
                    fn ($q) => $this->storeFilter($q, 'sale_returns')
                )
                ->select('return_date')
                ->selectRaw('SUM(total_refund) as total')
                ->groupBy('return_date')
                ->pluck('total', 'return_date');
            foreach ($rows as $date => $total) {
                $refunds[(string) $date] = (float) $total;
            }
        }

        $out = [];
        foreach (CarbonPeriod::create($from, $to->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $out[] = [
                'date' => $key,
                'label' => $day->translatedFormat('d M'),
                'short' => $day->format('d/m'),
                'total' => max(0, ($map[$key] ?? 0.0) - ($refunds[$key] ?? 0.0)),
                'is_today' => $day->isToday(),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{date: string, label: string, short: string, total: float, is_today: bool}>
     */
    public function monthlyCa(Carbon $from, Carbon $to): array
    {
        $map = [];
        if ($this->hasTable('sales')) {
            $rows = DB::connection('tenant')->table('sales')
                ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
                ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
                ->selectRaw($this->yearMonthSql('sale_date').' as ym')
                ->selectRaw('SUM(total) as total')
                ->groupBy(DB::raw($this->yearMonthSql('sale_date')))
                ->pluck('total', 'ym');
            foreach ($rows as $ym => $total) {
                $map[(string) $ym] = (float) $total;
            }
        }

        $refunds = [];
        if ($this->hasTable('sale_returns') && $this->hasColumn('sale_returns', 'total_refund')) {
            $rows = DB::connection('tenant')->table('sale_returns')
                ->where('status', 'confirmed')
                ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
                ->when(
                    $this->hasColumn('sale_returns', 'store_id'),
                    fn ($q) => $this->storeFilter($q, 'sale_returns')
                )
                ->selectRaw($this->yearMonthSql('return_date').' as ym')
                ->selectRaw('SUM(total_refund) as total')
                ->groupBy(DB::raw($this->yearMonthSql('return_date')))
                ->pluck('total', 'ym');
            foreach ($rows as $ym => $total) {
                $refunds[(string) $ym] = (float) $total;
            }
        }

        $out = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $out[] = [
                'date' => $key,
                'label' => $cursor->translatedFormat('M Y'),
                'short' => $cursor->translatedFormat('M'),
                'total' => max(0, ($map[$key] ?? 0.0) - ($refunds[$key] ?? 0.0)),
                'is_today' => $cursor->isSameMonth(now()),
            ];
            $cursor->addMonthNoOverflow();
        }

        return $out;
    }

    /**
     * @return array<int, array{name: string, total: float, percent: float, color: string}>
     */
    public function salesByCategory(Carbon $from, Carbon $to, int $limit = 6): array
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales') || ! $this->hasTable('items')) {
            return [];
        }

        $hasCategories = $this->hasTable('categories');
        $query = DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->when($hasCategories, fn ($q) => $q->leftJoin('categories', 'items.category_id', '=', 'categories.id'))
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->selectRaw($hasCategories
                ? "COALESCE(categories.name, 'Sans catégorie') as name"
                : "'Catalogue' as name")
            ->selectRaw('SUM(sale_lines.line_total) as total')
            ->when(
                $hasCategories,
                fn ($q) => $q->groupBy('categories.name'),
                fn ($q) => $q->groupBy(DB::raw("'Catalogue'"))
            )
            ->orderByDesc('total');

        $rows = $query->get();
        $grand = (float) $rows->sum('total');
        $colors = ['#3fa796', '#2563eb', '#8b5cf6', '#f59e0b', '#ec4899', '#64748b', '#0ea5e9'];
        $top = $rows->take($limit);
        $rest = $rows->slice($limit)->sum('total');
        $out = [];
        foreach ($top as $i => $row) {
            $total = (float) $row->total;
            $out[] = [
                'name' => (string) $row->name,
                'total' => $total,
                'percent' => $grand > 0 ? round($total / $grand * 100, 1) : 0.0,
                'color' => $colors[$i % count($colors)],
            ];
        }
        if ($rest > 0) {
            $out[] = [
                'name' => 'Autres',
                'total' => (float) $rest,
                'percent' => $grand > 0 ? round($rest / $grand * 100, 1) : 0.0,
                'color' => $colors[count($colors) - 1],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{method: string, label: string, total: float, percent: float, color: string}>
     */
    public function paymentsBreakdown(Carbon $from, Carbon $to): array
    {
        if (! $this->hasTable('payments') || ! $this->hasTable('sales')) {
            return [];
        }

        $rows = DB::connection('tenant')->table('payments')
            ->join('sales', 'payments.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->select('payments.method')
            ->selectRaw('SUM(payments.amount) as total')
            ->groupBy('payments.method')
            ->orderByDesc('total')
            ->get();

        $grand = (float) $rows->sum('total');
        $colors = [
            'cash' => '#3fa796',
            'mobile_money' => '#2563eb',
            'orange_money' => '#f97316',
            'mtn_money' => '#eab308',
            'card' => '#8b5cf6',
            'credit' => '#ef4444',
        ];
        $i = 0;
        $fallback = ['#0ea5e9', '#64748b', '#14b8a6'];

        return $rows->map(function ($row) use ($grand, $colors, &$i, $fallback) {
            $method = (string) $row->method;
            $total = (float) $row->total;
            $color = $colors[$method] ?? $fallback[$i++ % count($fallback)];

            return [
                'method' => $method,
                'label' => Payment::METHOD_LABELS[$method] ?? ucfirst($method),
                'total' => $total,
                'percent' => $grand > 0 ? round($total / $grand * 100, 1) : 0.0,
                'color' => $color,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{rank: int, item_id: int, name: string, sku: ?string, qty: float, ca: float, margin: float}>
     */
    public function topProducts(Carbon $from, Carbon $to, int $limit = 5): array
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales') || ! $this->hasTable('items')) {
            return [];
        }

        $rows = DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->select(
                'items.id as item_id',
                'items.name',
                'items.sku'
            )
            ->selectRaw('SUM(sale_lines.quantity) as qty')
            ->selectRaw('SUM(sale_lines.line_total) as ca')
            ->selectRaw('SUM(sale_lines.line_total) - SUM(sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * COALESCE(items.cost, 0)) as margin')
            ->groupBy('items.id', 'items.name', 'items.sku')
            ->orderByDesc('ca')
            ->limit($limit)
            ->get();

        return $rows->values()->map(fn ($row, $i) => [
            'rank' => $i + 1,
            'item_id' => (int) $row->item_id,
            'name' => (string) $row->name,
            'sku' => $row->sku,
            'qty' => (float) $row->qty,
            'ca' => (float) $row->ca,
            'margin' => (float) $row->margin,
        ])->all();
    }

    /**
     * @return array<int, array{key: string, tone: string, icon: string, title: string, value: string, hint: string, route: ?string}>
     */
    public function alerts(): array
    {
        $out = [];
        $stockouts = $this->stockoutCount();
        if ($stockouts > 0) {
            $out[] = [
                'key' => 'stockouts',
                'tone' => 'rose',
                'icon' => 'alert',
                'title' => 'Ruptures de stock',
                'value' => $stockouts.' produit'.($stockouts > 1 ? 's' : ''),
                'hint' => 'Quantité disponible ≤ 0',
                'route' => 'tenant.pharma-reporting.stock',
            ];
        }

        $expiring = $this->expiringBatchesCount(90);
        if ($expiring > 0) {
            $out[] = [
                'key' => 'expiring',
                'tone' => 'amber',
                'icon' => 'alert',
                'title' => 'Lots bientôt périmés',
                'value' => $expiring.' lot'.($expiring > 1 ? 's' : ''),
                'hint' => 'Expiration dans les 90 jours',
                'route' => 'tenant.pharma-reporting.alerts',
            ];
        }

        $dead = $this->deadStockCount(90);
        if ($dead > 0) {
            $out[] = [
                'key' => 'dead',
                'tone' => 'violet',
                'icon' => 'package',
                'title' => 'Stock dormant',
                'value' => $dead.' produit'.($dead > 1 ? 's' : ''),
                'hint' => 'Aucune vente depuis 90 jours',
                'route' => 'tenant.pharma-reporting.stock',
            ];
        }

        $overdue = $this->overdueDebtsCount();
        $overdueTotal = $this->overdueDebtsTotal();
        if ($overdue > 0) {
            $out[] = [
                'key' => 'debts',
                'tone' => 'rose',
                'icon' => 'wallet',
                'title' => 'Créances en retard',
                'value' => $overdue.' client'.($overdue > 1 ? 's' : ''),
                'hint' => fmt_money($overdueTotal).' à recouvrer',
                'route' => 'tenant.pharma-reporting.finance',
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{item_id: int, name: string, sku: ?string, available: float, reorder_point: ?float, kind: string}>
     */
    public function stockoutItems(int $limit = 50): array
    {
        return $this->stockAlertItems('out', $limit);
    }

    /**
     * @return array<int, array{item_id: int, name: string, sku: ?string, available: float, reorder_point: ?float, kind: string}>
     */
    public function lowStockItems(int $limit = 50): array
    {
        return $this->stockAlertItems('low', $limit);
    }

    /**
     * @return array<int, array{id: int, batch_number: string, item_name: string, expiry_date: string, quantity: float, days: int}>
     */
    public function expiringBatches(int $days = 90, int $limit = 50): array
    {
        if (! $this->hasTable('batches') || ! $this->hasTable('items')) {
            return [];
        }

        $today = now()->toDateString();
        $until = now()->addDays($days)->toDateString();
        $rows = DB::connection('tenant')->table('batches')
            ->join('items', 'batches.item_id', '=', 'items.id')
            ->where('batches.quantity', '>', 0)
            ->whereDate('batches.expiry_date', '>=', $today)
            ->whereDate('batches.expiry_date', '<=', $until)
            ->when(
                $this->hasColumn('batches', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'batches')
            )
            ->orderBy('batches.expiry_date')
            ->limit($limit)
            ->get([
                'batches.id',
                'batches.batch_number',
                'items.name as item_name',
                'batches.expiry_date',
                'batches.quantity',
            ]);

        return $rows->map(function ($row) {
            $expiry = Carbon::parse($row->expiry_date);

            return [
                'id' => (int) $row->id,
                'batch_number' => (string) $row->batch_number,
                'item_name' => (string) $row->item_name,
                'expiry_date' => $expiry->format('d/m/Y'),
                'quantity' => (float) $row->quantity,
                'days' => (int) now()->startOfDay()->diffInDays($expiry, false),
            ];
        })->all();
    }

    /**
     * @return array<int, array{item_id: int, name: string, sku: ?string, available: float, last_sale: ?string, days: int}>
     */
    public function deadStockItems(int $idleDays = 90, int $limit = 50): array
    {
        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return [];
        }

        $cutoff = now()->subDays($idleDays)->toDateString();
        $hasSales = $this->hasTable('sale_lines') && $this->hasTable('sales');

        $query = DB::connection('tenant')->table('items')
            ->join('stock_levels', 'items.id', '=', 'stock_levels.item_id')
            ->where('stock_levels.available_quantity', '>', 0)
            ->when(
                $this->hasColumn('stock_levels', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'stock_levels')
            );

        if ($hasSales) {
            $query->leftJoin(DB::raw('(
                SELECT sale_lines.item_id, MAX(sales.sale_date) as last_sale
                FROM sale_lines
                INNER JOIN sales ON sales.id = sale_lines.sale_id
                GROUP BY sale_lines.item_id
            ) as last_sales'), 'items.id', '=', 'last_sales.item_id')
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_sales.last_sale')
                        ->orWhere('last_sales.last_sale', '<', $cutoff);
                })
                ->orderBy('last_sales.last_sale')
                ->select('items.id as item_id', 'items.name', 'items.sku', 'stock_levels.available_quantity', 'last_sales.last_sale');
        } else {
            $query->orderBy('stock_levels.available_quantity', 'desc')
                ->select('items.id as item_id', 'items.name', 'items.sku', 'stock_levels.available_quantity');
        }

        return $query->limit($limit)->get()->map(function ($row) {
            $last = $row->last_sale ?? null;

            return [
                'item_id' => (int) $row->item_id,
                'name' => (string) $row->name,
                'sku' => $row->sku,
                'available' => (float) $row->available_quantity,
                'last_sale' => $last ? Carbon::parse($last)->format('d/m/Y') : null,
                'days' => $last ? (int) Carbon::parse($last)->diffInDays(now()) : 999,
            ];
        })->all();
    }

    /**
     * @return array<int, array{item_id: int, name: string, sku: ?string, category: string, qty: float, ca: float, cogs: float, margin: float, margin_rate: float}>
     */
    public function profitabilityByProduct(Carbon $from, Carbon $to, int $limit = 80): array
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales') || ! $this->hasTable('items')) {
            return [];
        }

        $hasCategories = $this->hasTable('categories');
        $rows = DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->when($hasCategories, fn ($q) => $q->leftJoin('categories', 'items.category_id', '=', 'categories.id'))
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->select(
                'items.id as item_id',
                'items.name',
                'items.sku'
            )
            ->selectRaw($hasCategories ? "COALESCE(categories.name, '—') as category" : "'—' as category")
            ->selectRaw('SUM(sale_lines.quantity) as qty')
            ->selectRaw('SUM(sale_lines.line_total) as ca')
            ->selectRaw('SUM(sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * COALESCE(items.cost, 0)) as cogs')
            ->groupBy('items.id', 'items.name', 'items.sku')
            ->when($hasCategories, fn ($q) => $q->groupBy('categories.name'))
            ->orderByDesc('ca')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $ca = (float) $row->ca;
            $cogs = (float) $row->cogs;
            $margin = $ca - $cogs;

            return [
                'item_id' => (int) $row->item_id,
                'name' => (string) $row->name,
                'sku' => $row->sku,
                'category' => (string) $row->category,
                'qty' => (float) $row->qty,
                'ca' => $ca,
                'cogs' => $cogs,
                'margin' => $margin,
                'margin_rate' => $ca > 0 ? round($margin / $ca * 100, 1) : 0.0,
            ];
        })->all();
    }

    /**
     * @return array<int, array{name: string, qty: float, ca: float, margin: float, margin_rate: float}>
     */
    public function profitabilityByCategory(Carbon $from, Carbon $to): array
    {
        $rows = $this->salesByCategory($from, $to, 20);
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('items')) {
            return collect($rows)->map(fn ($r) => [
                'name' => $r['name'],
                'qty' => 0.0,
                'ca' => $r['total'],
                'margin' => 0.0,
                'margin_rate' => 0.0,
            ])->all();
        }

        $hasCategories = $this->hasTable('categories');
        $stats = DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->when($hasCategories, fn ($q) => $q->leftJoin('categories', 'items.category_id', '=', 'categories.id'))
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->selectRaw($hasCategories ? "COALESCE(categories.name, 'Sans catégorie') as name" : "'Catalogue' as name")
            ->selectRaw('SUM(sale_lines.quantity) as qty')
            ->selectRaw('SUM(sale_lines.line_total) as ca')
            ->selectRaw('SUM(sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * COALESCE(items.cost, 0)) as cogs')
            ->when(
                $hasCategories,
                fn ($q) => $q->groupBy('categories.name'),
                fn ($q) => $q->groupBy(DB::raw("'Catalogue'"))
            )
            ->orderByDesc('ca')
            ->get();

        return $stats->map(function ($row) {
            $ca = (float) $row->ca;
            $cogs = (float) $row->cogs;
            $margin = $ca - $cogs;

            return [
                'name' => (string) $row->name,
                'qty' => (float) $row->qty,
                'ca' => $ca,
                'margin' => $margin,
                'margin_rate' => $ca > 0 ? round($margin / $ca * 100, 1) : 0.0,
            ];
        })->all();
    }

    /**
     * @param  array{category_id?: ?int, item_id?: ?int, user_id?: ?int, store_id?: ?int, payment_method?: string, search?: string}  $filters
     */
    private function salesFilteredQuery(Carbon $from, Carbon $to, array $filters = []): Builder
    {
        $query = DB::connection('tenant')->table('sales')
            ->leftJoin('clients', 'sales.client_id', '=', 'clients.id')
            ->leftJoin('users', 'sales.created_by', '=', 'users.id')
            ->when(
                $this->hasTable('stores') && $this->hasColumn('sales', 'store_id'),
                fn ($q) => $q->leftJoin('stores', 'sales.store_id', '=', 'stores.id')
            )
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'));

        if (! empty($filters['user_id'])) {
            $query->where('sales.created_by', (int) $filters['user_id']);
        }
        if (! empty($filters['store_id']) && $this->hasColumn('sales', 'store_id')) {
            $query->where('sales.store_id', (int) $filters['store_id']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('sales.sale_number', 'like', $term)
                    ->orWhere('clients.name', 'like', $term)
                    ->orWhere('users.name', 'like', $term);
            });
        }
        if (! empty($filters['payment_method']) && $this->hasTable('payments')) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from('payments')
                    ->whereColumn('payments.sale_id', 'sales.id')
                    ->where('payments.method', $filters['payment_method']);
            });
        }
        if ((! empty($filters['category_id']) || ! empty($filters['item_id'])) && $this->hasTable('sale_lines')) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from('sale_lines')
                    ->whereColumn('sale_lines.sale_id', 'sales.id');
                if (! empty($filters['item_id'])) {
                    $q->where('sale_lines.item_id', (int) $filters['item_id']);
                }
                if (! empty($filters['category_id']) && $this->hasTable('items')) {
                    $q->join('items', 'sale_lines.item_id', '=', 'items.id')
                        ->where('items.category_id', (int) $filters['category_id']);
                }
            });
        }

        return $query;
    }

    /**
     * @param  array{category_id?: ?int, item_id?: ?int, user_id?: ?int, store_id?: ?int, payment_method?: string, search?: string}  $filters
     * @return array{ca: float, sales_count: int, qty_sold: float, avg_basket: float, cogs: float, margin: float, margin_rate: float}
     */
    public function salesListKpis(Carbon $from, Carbon $to, array $filters = []): array
    {
        $empty = [
            'ca' => 0.0,
            'sales_count' => 0,
            'qty_sold' => 0.0,
            'avg_basket' => 0.0,
            'cogs' => 0.0,
            'margin' => 0.0,
            'margin_rate' => 0.0,
        ];
        if (! $this->hasTable('sales')) {
            return $empty;
        }

        $agg = (clone $this->salesFilteredQuery($from, $to, $filters))
            ->selectRaw('COUNT(DISTINCT sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as ca')
            ->first();

        $ca = (float) ($agg->ca ?? 0);
        $count = (int) ($agg->sales_count ?? 0);
        $qty = 0.0;
        $cogs = 0.0;

        if ($this->hasTable('sale_lines')) {
            $lineQuery = DB::connection('tenant')->table('sale_lines')
                ->whereIn('sale_lines.sale_id', $this->salesFilteredQuery($from, $to, $filters)->select('sales.id')->distinct())
                ->selectRaw('COALESCE(SUM(sale_lines.quantity), 0) as qty');
            if ($this->hasTable('items')) {
                $lineQuery->leftJoin('items', 'sale_lines.item_id', '=', 'items.id')
                    ->selectRaw('COALESCE(SUM(sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * COALESCE(items.cost, 0)), 0) as cogs');
            } else {
                $lineQuery->selectRaw('0 as cogs');
            }
            $lineAgg = $lineQuery->first();
            $qty = (float) ($lineAgg->qty ?? 0);
            $cogs = (float) ($lineAgg->cogs ?? 0);
        }

        $margin = $ca - $cogs;

        return [
            'ca' => $ca,
            'sales_count' => $count,
            'qty_sold' => $qty,
            'avg_basket' => $count > 0 ? $ca / $count : 0.0,
            'cogs' => $cogs,
            'margin' => $margin,
            'margin_rate' => $ca > 0 ? round($margin / $ca * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array{category_id?: ?int, item_id?: ?int, user_id?: ?int, store_id?: ?int, payment_method?: string, search?: string}  $filters
     */
    public function salesListQuery(Carbon $from, Carbon $to, array $filters = []): Builder
    {
        $query = $this->salesFilteredQuery($from, $to, $filters);

        $hasLines = $this->hasTable('sale_lines') && $this->hasTable('items');
        $hasPayments = $this->hasTable('payments');

        $query->select(
            'sales.id',
            'sales.sale_number',
            'sales.sale_date',
            'sales.total',
            'sales.created_at',
            'clients.name as client_name',
            'users.name as seller_name'
        )
            ->selectRaw($this->hasTable('stores') && $this->hasColumn('sales', 'store_id')
                ? 'stores.name as store_name'
                : 'NULL as store_name');

        if ($hasLines) {
            $query->selectRaw('(SELECT COUNT(DISTINCT sl.item_id) FROM sale_lines sl WHERE sl.sale_id = sales.id) as products_count')
                ->selectRaw('(SELECT COALESCE(SUM(sl.quantity), 0) FROM sale_lines sl WHERE sl.sale_id = sales.id) as qty_total')
                ->selectRaw('(SELECT COALESCE(SUM(sl.quantity * COALESCE(sl.conversion_factor, 1) * COALESCE(it.cost, 0)), 0)
                    FROM sale_lines sl INNER JOIN items it ON it.id = sl.item_id WHERE sl.sale_id = sales.id) as cogs');
        } else {
            $query->selectRaw('0 as products_count')->selectRaw('0 as qty_total')->selectRaw('0 as cogs');
        }

        if ($hasPayments) {
            $query->selectRaw('(SELECT '.$this->concatDistinctSql('p.method').' FROM payments p WHERE p.sale_id = sales.id) as payment_methods')
                ->selectRaw("(SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.sale_id = sales.id AND p.method != 'credit') as paid_amount")
                ->selectRaw("(SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.sale_id = sales.id AND p.method = 'credit') as credit_amount");
        } else {
            $query->selectRaw('NULL as payment_methods')->selectRaw('sales.total as paid_amount')->selectRaw('0 as credit_amount');
        }

        return $query->orderByDesc('sales.sale_date')->orderByDesc('sales.id');
    }

    /**
     * @return array<int, array{id: int, order_number: string, order_date: string, provider: string, status: string, status_label: string, total: float}>
     */
    public function purchaseOrders(Carbon $from, Carbon $to, int $limit = 50): array
    {
        if (! $this->hasTable('purchase_orders')) {
            return [];
        }

        $hasProviders = $this->hasTable('providers');
        $rows = DB::connection('tenant')->table('purchase_orders')
            ->when($hasProviders, fn ($q) => $q->leftJoin('providers', 'purchase_orders.provider_id', '=', 'providers.id'))
            ->whereBetween('purchase_orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->when(
                $this->hasColumn('purchase_orders', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'purchase_orders')
            )
            ->orderByDesc('purchase_orders.order_date')
            ->limit($limit)
            ->get([
                'purchase_orders.id',
                'purchase_orders.order_number',
                'purchase_orders.order_date',
                'purchase_orders.status',
                'purchase_orders.total',
                $hasProviders ? 'providers.name as provider_name' : DB::raw("NULL as provider_name"),
            ]);

        $labels = [
            'draft' => 'Brouillon',
            'confirmed' => 'Confirmée',
            'partial' => 'Réception partielle',
            'sent' => 'Réception partielle',
            'received' => 'Réceptionnée',
            'cancelled' => 'Annulée',
        ];

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'order_number' => (string) $row->order_number,
            'order_date' => Carbon::parse($row->order_date)->format('d/m/Y'),
            'provider' => (string) ($row->provider_name ?? '—'),
            'status' => (string) $row->status,
            'status_label' => $labels[$row->status] ?? (string) $row->status,
            'total' => (float) $row->total,
        ])->all();
    }

    /**
     * @return array<int, array{provider: string, orders: int, total: float}>
     */
    public function purchasesByProvider(Carbon $from, Carbon $to, int $limit = 10): array
    {
        if (! $this->hasTable('purchase_orders')) {
            return [];
        }
        $hasProviders = $this->hasTable('providers');

        return DB::connection('tenant')->table('purchase_orders')
            ->when($hasProviders, fn ($q) => $q->leftJoin('providers', 'purchase_orders.provider_id', '=', 'providers.id'))
            ->whereBetween('purchase_orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->where('purchase_orders.status', '!=', 'cancelled')
            ->when(
                $this->hasColumn('purchase_orders', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'purchase_orders')
            )
            ->selectRaw($hasProviders ? "COALESCE(providers.name, 'Sans fournisseur') as provider" : "'Fournisseurs' as provider")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(purchase_orders.total) as total')
            ->when($hasProviders, fn ($q) => $q->groupBy('providers.name'), fn ($q) => $q->groupBy(DB::raw("'Fournisseurs'")))
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'provider' => (string) $row->provider,
                'orders' => (int) $row->orders,
                'total' => (float) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{category: string, total: float}>
     */
    public function expensesByCategory(Carbon $from, Carbon $to): array
    {
        if (! $this->hasTable('expenses')) {
            return [];
        }
        $hasCat = $this->hasTable('expense_categories');

        return DB::connection('tenant')->table('expenses')
            ->when($hasCat, fn ($q) => $q->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id'))
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('expenses.status', ['approved', 'paid'])
            ->when(true, fn ($q) => $this->storeFilter($q, 'expenses'))
            ->selectRaw($hasCat ? "COALESCE(expense_categories.name, 'Autres') as category" : "'Dépenses' as category")
            ->selectRaw('SUM(expenses.amount) as total')
            ->when(
                $hasCat,
                fn ($q) => $q->groupBy('expense_categories.name'),
                fn ($q) => $q->groupBy(DB::raw("'Dépenses'"))
            )
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category,
                'total' => (float) $row->total,
            ])
            ->all();
    }

    /**
     * @return array{receivables: float, overdue_count: int, overdue_total: float, collected: float}
     */
    public function debtsSnapshot(Carbon $from, Carbon $to): array
    {
        $empty = ['receivables' => 0.0, 'overdue_count' => 0, 'overdue_total' => 0.0, 'collected' => 0.0];
        if (! $this->hasTable('client_debts') && ! $this->hasTable('debts')) {
            if (app()->bound(\InovCom\Kernel\Contracts\DebtsApi::class)) {
                $api = app(\InovCom\Kernel\Contracts\DebtsApi::class);
                $s = $api->getSummary($from->toDateString(), $to->toDateString());

                return [
                    'receivables' => (float) ($s['receivables_total'] ?? 0),
                    'overdue_count' => (int) ($s['overdue_count'] ?? 0),
                    'overdue_total' => (float) ($s['overdue_total'] ?? 0),
                    'collected' => (float) ($s['collected_in_period'] ?? 0),
                ];
            }

            return $empty;
        }

        $table = $this->hasTable('client_debts') ? 'client_debts' : 'debts';
        $balanceCol = $this->hasColumn($table, 'balance') ? 'balance' : 'amount';
        $statusCol = $this->hasColumn($table, 'status');

        $query = DB::connection('tenant')->table($table);
        if ($statusCol) {
            $query->whereNotIn('status', ['cancelled', 'paid', 'settled']);
        }
        $receivables = (float) (clone $query)->sum($balanceCol);

        $overdueQ = DB::connection('tenant')->table($table);
        if ($statusCol) {
            $overdueQ->whereNotIn('status', ['cancelled', 'paid', 'settled']);
        }
        if ($this->hasColumn($table, 'due_date')) {
            $overdueQ->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString());
        }

        return [
            'receivables' => $receivables,
            'overdue_count' => (int) (clone $overdueQ)->count(),
            'overdue_total' => (float) (clone $overdueQ)->sum($balanceCol),
            'collected' => 0.0,
        ];
    }

    /**
     * @return array{categories: array<int, array{id: int, name: string}>, users: array<int, array{id: int, name: string}>, stores: array<int, array{id: int, name: string}>, methods: array<string, string>}
     */
    public function filterOptions(): array
    {
        $categories = [];
        if ($this->hasTable('categories')) {
            $categories = DB::connection('tenant')->table('categories')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
                ->all();
        }

        $users = [];
        if ($this->hasTable('users')) {
            $usersQuery = DB::connection('tenant')->table('users')->orderBy('name');
            if ($this->hasColumn('users', 'is_active')) {
                $usersQuery->where('is_active', true);
            }
            $users = $usersQuery->get(['id', 'name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
                ->all();
        }

        $stores = [];
        if ($this->hasTable('stores')) {
            $q = DB::connection('tenant')->table('stores')->orderBy('name');
            if ($this->hasColumn('stores', 'is_active')) {
                $q->where('is_active', true);
            }
            $stores = $q->get(['id', 'name'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name])
                ->all();
        }

        return [
            'categories' => $categories,
            'users' => $users,
            'stores' => $stores,
            'methods' => Payment::METHOD_LABELS,
        ];
    }

    public function paymentMethodLabel(?string $methods): string
    {
        if (! $methods) {
            return '—';
        }
        $parts = array_filter(array_map('trim', explode(',', $methods)));
        $labels = array_map(fn ($m) => Payment::METHOD_LABELS[$m] ?? ucfirst($m), $parts);

        return implode(' · ', $labels) ?: '—';
    }

    public function saleStatus(float $creditAmount, float $paidAmount, float $total): array
    {
        if ($creditAmount > 0.01) {
            return ['key' => 'credit', 'label' => 'Crédit'];
        }
        if ($paidAmount + 0.01 >= $total) {
            return ['key' => 'paid', 'label' => 'Validée'];
        }

        return ['key' => 'open', 'label' => 'En cours'];
    }

    public function netSales(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('sales')) {
            return 0.0;
        }
        $gross = (float) DB::connection('tenant')->table('sales')
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->sum('total');

        $refunds = 0.0;
        if (class_exists(SaleReturnsService::class)) {
            $refunds = SaleReturnsService::totalRefundsBetween($from->toDateString(), $to->toDateString());
        }

        return max(0, $gross - $refunds);
    }

    public function salesCount(Carbon $from, Carbon $to): int
    {
        if (! $this->hasTable('sales')) {
            return 0;
        }

        return (int) DB::connection('tenant')->table('sales')
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->count();
    }

    public function qtySold(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->sum('sale_lines.quantity');
    }

    public function distinctProducts(Carbon $from, Carbon $to): int
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales')) {
            return 0;
        }

        return (int) DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->distinct()
            ->count('sale_lines.item_id');
    }

    public function cogs(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('sale_lines') || ! $this->hasTable('sales') || ! $this->hasTable('items')) {
            return 0.0;
        }

        $cogs = (float) DB::connection('tenant')->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->whereBetween('sales.sale_date', [$from->toDateString(), $to->toDateString()])
            ->when(true, fn ($q) => $this->storeFilter($q, 'sales'))
            ->selectRaw('COALESCE(SUM(sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * COALESCE(items.cost, 0)), 0) as total')
            ->value('total');

        if ($this->hasTable('sale_returns') && $this->hasTable('sale_return_lines')) {
            $returned = (float) DB::connection('tenant')->table('sale_return_lines')
                ->join('sale_returns', 'sale_return_lines.sale_return_id', '=', 'sale_returns.id')
                ->join('items', 'sale_return_lines.item_id', '=', 'items.id')
                ->where('sale_returns.status', 'confirmed')
                ->whereBetween('sale_returns.return_date', [$from->toDateString(), $to->toDateString()])
                ->when(
                    $this->hasColumn('sale_returns', 'store_id'),
                    fn ($q) => $this->storeFilter($q, 'sale_returns')
                )
                ->selectRaw('COALESCE(SUM(COALESCE(sale_return_lines.quantity_base, sale_return_lines.quantity) * COALESCE(items.cost, 0)), 0) as total')
                ->value('total');
            $cogs = max(0, $cogs - $returned);
        }

        return $cogs;
    }

    public function expenses(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('expenses')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')->table('expenses')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['approved', 'paid'])
            ->when(true, fn ($q) => $this->storeFilter($q, 'expenses'))
            ->sum('amount');
    }

    public function losses(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('loss_records')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')->table('loss_records')
            ->whereBetween('loss_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'confirmed')
            ->when(true, fn ($q) => $this->storeFilter($q, 'loss_records'))
            ->sum('value');
    }

    public function purchasesReceived(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('purchase_orders')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')->table('purchase_orders')
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['received', 'partial'])
            ->when(
                $this->hasColumn('purchase_orders', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'purchase_orders')
            )
            ->sum('total');
    }

    public function stockValue(): float
    {
        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')->table('stock_levels')
            ->join('items', 'stock_levels.item_id', '=', 'items.id')
            ->when(
                $this->hasColumn('stock_levels', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'stock_levels')
            )
            ->selectRaw('COALESCE(SUM(stock_levels.available_quantity * COALESCE(items.cost, 0)), 0) as total')
            ->value('total');
    }

    public function stockoutCount(): int
    {
        if (! $this->hasTable('stock_levels')) {
            return 0;
        }

        return (int) DB::connection('tenant')->table('stock_levels')
            ->where('available_quantity', '<=', 0)
            ->when(
                $this->hasColumn('stock_levels', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'stock_levels')
            )
            ->count();
    }

    public function expiringBatchesCount(int $days = 90): int
    {
        if (! $this->hasTable('batches')) {
            return 0;
        }

        return (int) DB::connection('tenant')->table('batches')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->when(
                $this->hasColumn('batches', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'batches')
            )
            ->count();
    }

    public function lowStockCount(): int
    {
        if (! $this->hasTable('stock_levels')) {
            return 0;
        }

        return (int) DB::connection('tenant')->table('stock_levels')
            ->whereNotNull('reorder_point')
            ->where('available_quantity', '>', 0)
            ->whereRaw('available_quantity <= reorder_point')
            ->when(
                $this->hasColumn('stock_levels', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'stock_levels')
            )
            ->count();
    }

    public function deadStockCount(int $idleDays = 90): int
    {
        return count($this->deadStockItems($idleDays, 500));
    }

    public function overdueDebtsCount(): int
    {
        return $this->debtsSnapshot(now()->startOfMonth(), now())['overdue_count'];
    }

    public function overdueDebtsTotal(): float
    {
        return $this->debtsSnapshot(now()->startOfMonth(), now())['overdue_total'];
    }

    /**
     * @return array<int, array{item_id: int, name: string, sku: ?string, available: float, reorder_point: ?float, kind: string}>
     */
    private function stockAlertItems(string $kind, int $limit): array
    {
        if (! $this->hasTable('stock_levels') || ! $this->hasTable('items')) {
            return [];
        }

        $query = DB::connection('tenant')->table('items')
            ->join('stock_levels', 'items.id', '=', 'stock_levels.item_id')
            ->when(
                $this->hasColumn('stock_levels', 'store_id'),
                fn ($q) => $this->storeFilter($q, 'stock_levels')
            );

        if ($kind === 'out') {
            $query->where('stock_levels.available_quantity', '<=', 0);
        } else {
            $query->whereNotNull('stock_levels.reorder_point')
                ->where('stock_levels.available_quantity', '>', 0)
                ->whereRaw('stock_levels.available_quantity <= stock_levels.reorder_point');
        }

        return $query->orderBy('stock_levels.available_quantity')
            ->limit($limit)
            ->get([
                'items.id as item_id',
                'items.name',
                'items.sku',
                'stock_levels.available_quantity',
                'stock_levels.reorder_point',
            ])
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'name' => (string) $row->name,
                'sku' => $row->sku,
                'available' => (float) $row->available_quantity,
                'reorder_point' => $row->reorder_point !== null ? (float) $row->reorder_point : null,
                'kind' => $kind,
            ])
            ->all();
    }

    private function storeFilter(Builder $query, string $table): Builder
    {
        if (! class_exists(StoreContextService::class)) {
            return $query;
        }
        $storeId = app(StoreContextService::class)->currentStoreId();
        if ($storeId && $this->hasColumn($table, 'store_id')) {
            $query->where("{$table}.store_id", $storeId);
        }

        return $query;
    }

    private function hasTable(string $table): bool
    {
        return Schema::connection('tenant')->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::connection('tenant')->hasColumn($table, $column);
    }

    private function isPgsql(): bool
    {
        return DB::connection('tenant')->getDriverName() === 'pgsql';
    }

    private function yearMonthSql(string $column): string
    {
        return $this->isPgsql()
            ? "to_char({$column}, 'YYYY-MM')"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function concatDistinctSql(string $column): string
    {
        return $this->isPgsql()
            ? "STRING_AGG(DISTINCT {$column}::text, ',')"
            : "GROUP_CONCAT(DISTINCT {$column})";
    }
}
