<?php

namespace InovCom\Losses\Services;

use App\Services\StoreContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\LossesApi;

class LossesApiService implements LossesApi
{
    public function getPeriodTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('loss_records')) {
            return 0.0;
        }

        $query = DB::connection('tenant')
            ->table('loss_records')
            ->whereBetween('loss_date', [$startDate, $endDate])
            ->where('status', 'confirmed');

        $storeId = app(StoreContextService::class)->currentStoreId();
        if ($storeId && Schema::connection('tenant')->hasColumn('loss_records', 'store_id')) {
            $query->where('loss_records.store_id', $storeId);
        }

        return (float) $query->sum('value');
    }

    public function getDailyTrend(string $startDate, string $endDate): array
    {
        $out = $this->fillDailyRange($startDate, $endDate, 0.0);
        if (! Schema::connection('tenant')->hasTable('loss_records')) {
            return $out;
        }

        $query = DB::connection('tenant')
            ->table('loss_records')
            ->whereBetween('loss_date', [$startDate, $endDate])
            ->where('status', 'confirmed');

        $storeId = app(StoreContextService::class)->currentStoreId();
        if ($storeId && Schema::connection('tenant')->hasColumn('loss_records', 'store_id')) {
            $query->where('loss_records.store_id', $storeId);
        }

        $rows = $query->select('loss_date')
            ->selectRaw('SUM(value) as total')
            ->groupBy('loss_date')
            ->orderBy('loss_date')
            ->get();

        foreach ($rows as $r) {
            $out[$r->loss_date] = (float) $r->total;
        }

        return $out;
    }

    public function getByReason(string $startDate, string $endDate): array
    {
        if (! Schema::connection('tenant')->hasTable('loss_records')
            || ! Schema::connection('tenant')->hasTable('loss_reasons')) {
            return [];
        }

        $rows = DB::connection('tenant')
            ->table('loss_records')
            ->join('loss_reasons', 'loss_records.loss_reason_id', '=', 'loss_reasons.id')
            ->whereBetween('loss_records.loss_date', [$startDate, $endDate])
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
     * @return array<string, float>
     */
    private function fillDailyRange(string $startDate, string $endDate, float $default): array
    {
        $existing = [];
        $curr = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();
        while ($curr->lte($end)) {
            $existing[$curr->format('Y-m-d')] = $default;
            $curr->addDay();
        }

        return $existing;
    }
}
