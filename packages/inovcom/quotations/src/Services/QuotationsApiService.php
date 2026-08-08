<?php

namespace InovCom\Quotations\Services;

use App\Services\StoreContextService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\QuotationsApi;

class QuotationsApiService implements QuotationsApi
{
    public function getPeriodSummary(string $startDate, string $endDate): array
    {
        $empty = [
            'count' => 0,
            'total' => 0.0,
            'by_status' => [],
            'accepted_count' => 0,
            'accepted_total' => 0.0,
        ];

        if (! Schema::connection('tenant')->hasTable('quotations')) {
            return $empty;
        }

        $rows = DB::connection('tenant')
            ->table('quotations')
            ->whereBetween('quote_date', [$startDate, $endDate])
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
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

        return [
            'count' => $count,
            'total' => $total,
            'by_status' => $byStatus,
            'accepted_count' => (int) ($byStatus['accepted']['count'] ?? 0),
            'accepted_total' => (float) ($byStatus['accepted']['total'] ?? 0),
        ];
    }

    public function getClientTotalsInPeriod(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('quotations')
            || ! Schema::connection('tenant')->hasTable('clients')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('quotations')
            ->join('clients', 'quotations.client_id', '=', 'clients.id')
            ->whereBetween('quotations.quote_date', [$startDate, $endDate])
            ->where('quotations.status', '!=', 'cancelled')
            ->when(true, fn ($q) => $this->applyStoreFilter($q))
            ->select('clients.id as client_id', 'clients.name as client_name')
            ->selectRaw('COALESCE(SUM(quotations.total), 0) as quotation_total')
            ->selectRaw('COUNT(*) as quotation_count')
            ->groupBy('clients.id', 'clients.name')
            ->get();

        return $rows->map(fn ($r) => [
            'client_id' => (int) $r->client_id,
            'client_name' => (string) $r->client_name,
            'quotation_total' => (float) $r->quotation_total,
            'quotation_count' => (int) $r->quotation_count,
        ])->all();
    }

    private function applyStoreFilter(Builder $query): Builder
    {
        $storeId = app(StoreContextService::class)->currentStoreId();
        if (
            $storeId
            && Schema::connection('tenant')->hasColumn('quotations', 'store_id')
        ) {
            $query->where('quotations.store_id', $storeId);
        }

        return $query;
    }
}
