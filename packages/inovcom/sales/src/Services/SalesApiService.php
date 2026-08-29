<?php

namespace InovCom\Sales\Services;

use App\Services\StoreContextService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\SalesApi;
use InovCom\Sales\Models\Payment;
use InovCom\Sales\Models\Sale;

class SalesApiService implements SalesApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0.0;
        }

        $gross = (float) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->sum('total');

        if (class_exists(SaleReturnsService::class)) {
            return max(0, $gross - SaleReturnsService::totalRefundsBetween($startDate, $endDate));
        }

        return $gross;
    }

    public function getPeriodCount(string $startDate, string $endDate): int
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->count();
    }

    public function getCostOfGoodsSold(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('sale_lines')
            || ! Schema::connection('tenant')->hasTable('sales')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return 0.0;
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
                ->whereBetween('sales.sale_date', [$startDate, $endDate])
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
                ->whereBetween('sales.sale_date', [$startDate, $endDate])
                ->selectRaw('COALESCE(SUM(
                    sale_lines.quantity * COALESCE(sale_lines.conversion_factor, 1) * items.cost
                ), 0) as total')
                ->value('total');
        }

        return (float) $sum;
    }

    public function getDailySalesTrend(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
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

        $curr = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();
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

    public function getDailyCogsTrend(string $startDate, string $endDate): array
    {
        $empty = $this->fillDailyRange($startDate, $endDate, 0.0);
        if (! Schema::connection('tenant')->hasTable('sale_lines')
            || ! Schema::connection('tenant')->hasTable('sales')
            || ! Schema::connection('tenant')->hasTable('items')) {
            return $empty;
        }

        $hasUnitPrices = Schema::connection('tenant')->hasTable('item_unit_prices');
        $dateCol = 'sales.sale_date';

        $query = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->whereBetween($dateCol, [$startDate, $endDate]);
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

        return $this->fillDailyRange($startDate, $endDate, 0.0, $out);
    }

    public function getTopProductsByRevenue(string $startDate, string $endDate, int $limit = 10): array
    {
        return $this->topProducts($startDate, $endDate, $limit, 'revenue');
    }

    public function getTopProductsByQuantity(string $startDate, string $endDate, int $limit = 10): array
    {
        return $this->topProducts($startDate, $endDate, $limit, 'quantity');
    }

    public function getTopProductsForClient(
        string $startDate,
        string $endDate,
        int $clientId,
        int $limit = 10,
        string $sortBy = 'revenue'
    ): array {
        if (! Schema::connection('tenant')->hasTable('sale_lines')
            || ! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $orderCol = $sortBy === 'quantity' ? 'quantity' : 'revenue';

        $q = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->where('sales.client_id', $clientId)
            ->when(true, fn ($query) => $this->applyStoreFilter($query, 'sales'))
            ->select('sale_lines.item_id', 'sale_lines.item_name', 'sale_lines.item_sku')
            ->selectRaw('SUM(sale_lines.quantity) as quantity')
            ->selectRaw('SUM(sale_lines.line_total) as revenue')
            ->groupBy('sale_lines.item_id', 'sale_lines.item_name', 'sale_lines.item_sku')
            ->orderByDesc($orderCol)
            ->limit($limit)
            ->get();

        return $q->map(fn ($r) => [
            'item_name' => $r->item_name,
            'item_sku' => $r->item_sku,
            'quantity' => (float) $r->quantity,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    public function getClientRevenueInPeriod(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('sales')
            || ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('sales')
            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->whereNotNull('sales.client_id')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->select('clients.id as client_id', 'clients.name as client_name')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as pos_revenue')
            ->selectRaw('COUNT(*) as pos_sale_count')
            ->groupBy('clients.id', 'clients.name')
            ->get();

        return $rows->map(fn ($r) => [
            'client_id' => (int) $r->client_id,
            'client_name' => (string) $r->client_name,
            'pos_revenue' => (float) $r->pos_revenue,
            'pos_sale_count' => (int) $r->pos_sale_count,
        ])->all();
    }

    public function getDistinctClientsCount(string $startDate, string $endDate): int
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return 0;
        }

        return (int) DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->whereNotNull('client_id')
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->count(DB::raw('DISTINCT client_id'));
    }

    public function hasStoreDimension(): bool
    {
        return Schema::connection('tenant')->hasTable('stores')
            && Schema::connection('tenant')->hasTable('sales')
            && Schema::connection('tenant')->hasColumn('sales', 'store_id');
    }

    public function getStorePerformance(string $startDate, string $endDate): array
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
            ->leftJoin('sales', function ($join) use ($startDate, $endDate) {
                $join->on('stores.id', '=', 'sales.store_id')
                    ->whereBetween('sales.sale_date', [$startDate, $endDate]);
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

    public function getTopSalesByTotal(string $startDate, string $endDate, int $limit = 10): array
    {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $query = DB::connection('tenant')
            ->table('sales')
            ->whereBetween('sale_date', [$startDate, $endDate])
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

    public function listSales(
        string $startDate,
        string $endDate,
        ?int $clientId = null,
        int $limit = 100
    ): array {
        if (! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $sales = DB::connection('tenant')
            ->table('sales')
            ->leftJoin('clients', 'clients.id', '=', 'sales.client_id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
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

        return $sales->map(fn ($s) => [
            'sale_number' => $s->sale_number,
            'sale_date' => $s->sale_date,
            'total' => (float) $s->total,
            'client_name' => $s->client_name,
        ])->all();
    }

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity: float, revenue: float}>
     */
    private function topProducts(string $startDate, string $endDate, int $limit, string $orderCol): array
    {
        if (! Schema::connection('tenant')->hasTable('sale_lines')
            || ! Schema::connection('tenant')->hasTable('sales')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('sale_lines')
            ->join('sales', 'sale_lines.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->when(true, fn ($q) => $this->applyStoreFilter($q, 'sales'))
            ->select([
                'sale_lines.item_id',
                DB::raw('MAX(sale_lines.item_name) as item_name'),
                DB::raw('MAX(sale_lines.item_sku) as item_sku'),
                DB::raw('SUM(sale_lines.quantity) as quantity'),
                DB::raw('SUM(sale_lines.line_total) as revenue'),
            ])
            ->groupBy('sale_lines.item_id')
            ->orderByDesc($orderCol)
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
     * @param  array<string, float>  $existing
     * @return array<string, float>
     */
    private function fillDailyRange(string $startDate, string $endDate, float $default, array $existing = []): array
    {
        $curr = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();
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

    public function syncLinkedDebtCollections(
        int $saleId,
        float $outstandingCredit,
        string $debtReference,
        array $collections
    ): bool {
        if ($saleId <= 0
            || ! Schema::connection('tenant')->hasTable('payments')
            || ! Schema::connection('tenant')->hasTable('sales')) {
            return false;
        }

        $sale = Sale::query()->find($saleId);
        if (! $sale) {
            return false;
        }

        $notePrefix = 'Encaissement dette '.$debtReference;
        $existing = Payment::query()
            ->where('sale_id', $saleId)
            ->where('notes', 'like', $notePrefix.'%')
            ->get();
        $existingSum = round((float) $existing->sum('amount'), 2);
        $collectionsSum = round(array_sum(array_map(
            static fn (array $row) => (float) ($row['amount'] ?? 0),
            $collections
        )), 2);

        if ($collectionsSum - $existingSum > 0.01) {
            if ($existingSum < 0.01) {
                foreach ($collections as $row) {
                    $this->createDebtCollectionPayment($sale, $row, $notePrefix);
                }
            } else {
                $last = $collections === [] ? null : $collections[array_key_last($collections)];
                if (is_array($last)) {
                    $last['amount'] = round($collectionsSum - $existingSum, 2);
                    $this->createDebtCollectionPayment($sale, $last, $notePrefix);
                }
            }
        }

        $currentCredit = round((float) Payment::query()
            ->where('sale_id', $saleId)
            ->where('method', 'credit')
            ->sum('amount'), 2);
        $targetCredit = round(max(0, $outstandingCredit), 2);
        $toReduce = round($currentCredit - $targetCredit, 2);
        if ($toReduce > 0.01) {
            $this->reduceCreditPayments($saleId, $toReduce);
        }

        return true;
    }

    /**
     * @param  array{amount?: float, method?: string, reference?: string|null, user_id?: int|null}  $row
     */
    private function createDebtCollectionPayment(Sale $sale, array $row, string $notePrefix): void
    {
        $amount = round((float) ($row['amount'] ?? 0), 2);
        if ($amount <= 0.01) {
            return;
        }

        $method = $this->mapDebtPaymentMethod((string) ($row['method'] ?? 'cash'));
        $payment = new Payment();
        $payment->sale_id = $sale->id;
        $payment->method = $method;
        $payment->mobile_money_provider = in_array($method, ['orange_money', 'mtn_money'], true) ? $method : null;
        $payment->transaction_reference = trim((string) ($row['reference'] ?? '')) ?: null;
        $payment->amount = $amount;
        $payment->notes = $notePrefix;
        $payment->received_by = isset($row['user_id']) ? (int) $row['user_id'] : null;

        if (Schema::connection('tenant')->hasColumn('payments', 'currency_code')) {
            $payment->currency_code = $sale->currency_code;
            if (Schema::connection('tenant')->hasColumn('payments', 'exchange_rate_to_default')) {
                $payment->exchange_rate_to_default = $sale->exchange_rate_to_default ?: 1;
            }
            if (Schema::connection('tenant')->hasColumn('payments', 'amount_in_default')) {
                $payment->amount_in_default = $sale->total_in_default !== null && (float) $sale->total > 0
                    ? round($amount * ((float) $sale->total_in_default / (float) $sale->total), 2)
                    : $amount;
            }
        }

        $payment->save();
    }

    private function reduceCreditPayments(int $saleId, float $amount): void
    {
        $remaining = round($amount, 2);
        $credits = Payment::query()
            ->where('sale_id', $saleId)
            ->where('method', 'credit')
            ->orderByDesc('id')
            ->get();

        foreach ($credits as $payment) {
            if ($remaining <= 0.01) {
                break;
            }

            $current = (float) $payment->amount;
            if ($current <= $remaining + 0.01) {
                $remaining = round($remaining - $current, 2);
                $payment->delete();
                continue;
            }

            $payment->amount = round($current - $remaining, 2);
            if (Schema::connection('tenant')->hasColumn('payments', 'amount_in_default')
                && $payment->amount_in_default !== null
                && $current > 0) {
                $payment->amount_in_default = round(
                    (float) $payment->amount_in_default * ((float) $payment->amount / $current),
                    2
                );
            }
            $payment->save();
            $remaining = 0;
        }
    }

    private function mapDebtPaymentMethod(string $method): string
    {
        return match ($method) {
            'cash', 'mobile_money', 'orange_money', 'mtn_money', 'card', 'check', 'bank_transfer', 'other' => $method,
            default => 'cash',
        };
    }

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
}
