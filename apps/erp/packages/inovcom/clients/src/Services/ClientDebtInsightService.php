<?php

namespace InovCom\Clients\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientDebtInsightService
{
    public function moduleAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('debts');
    }

    /**
     * @return array{
     *   outstanding: float,
     *   open_count: int,
     *   overdue_count: int,
     *   has_debt: bool,
     *   has_overdue: bool,
     *   level: string
     * }
     */
    public function forClient(int $clientId): array
    {
        $map = $this->forClientIds([$clientId]);

        return $map[$clientId] ?? $this->emptySummary();
    }

    /**
     * @param  array<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    public function forClientIds(array $clientIds): array
    {
        $result = [];
        foreach ($clientIds as $id) {
            $result[(int) $id] = $this->emptySummary();
        }

        if (!$this->moduleAvailable() || $clientIds === []) {
            return $result;
        }

        $rows = DB::connection('tenant')
            ->table('debts')
            ->whereIn('client_id', $clientIds)
            ->where('balance', '>', 0)
            ->groupBy('client_id')
            ->selectRaw('client_id')
            ->selectRaw('COALESCE(SUM(balance), 0) as outstanding')
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw("SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count")
            ->get();

        foreach ($rows as $row) {
            $outstanding = (float) $row->outstanding;
            $overdueCount = (int) $row->overdue_count;
            $result[(int) $row->client_id] = $this->buildSummary(
                $outstanding,
                (int) $row->open_count,
                $overdueCount
            );
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forPaginator(LengthAwarePaginator $paginator): array
    {
        $ids = collect($paginator->items())->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $this->forClientIds($ids);
    }

    private function buildSummary(float $outstanding, int $openCount, int $overdueCount): array
    {
        $hasDebt = $outstanding > 0.009;
        $hasOverdue = $overdueCount > 0;

        $level = 'clear';
        if ($hasOverdue) {
            $level = 'overdue';
        } elseif ($hasDebt) {
            $level = 'active';
        }

        return [
            'outstanding' => $outstanding,
            'open_count' => $openCount,
            'overdue_count' => $overdueCount,
            'has_debt' => $hasDebt,
            'has_overdue' => $hasOverdue,
            'level' => $level,
        ];
    }

    public function emptySummary(): array
    {
        return $this->buildSummary(0.0, 0, 0);
    }
}
