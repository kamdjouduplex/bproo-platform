<?php

namespace InovCom\Achats\Adapters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Achats\Models\PurchaseOrder;
use InovCom\Kernel\Contracts\PurchasingApi;

class BatPurchasingApiAdapter implements PurchasingApi
{
    public function findPurchaseOrder(int $id): ?object
    {
        if (! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return null;
        }

        return PurchaseOrder::on('tenant')->find($id);
    }

    public function findPurchaseOrderByNumber(string $number): ?object
    {
        if (! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return null;
        }

        return PurchaseOrder::on('tenant')
            ->where('code', $number)
            ->first();
    }

    public function purchaseOrderExists(int $id): bool
    {
        return $this->findPurchaseOrder($id) !== null;
    }

    public function getTotal(int $purchaseOrderId): float
    {
        $order = $this->findPurchaseOrder($purchaseOrderId);
        if (! $order) {
            return 0.0;
        }

        return Schema::connection('tenant')->hasColumn('purchase_orders', 'total_ht')
            ? (float) $order->total_ht
            : 0.0;
    }

    public function getPeriodReceivedTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return 0.0;
        }

        $dateCol = Schema::connection('tenant')->hasColumn('purchase_orders', 'received_at')
            ? 'received_at'
            : $this->periodDateColumn();
        $totalCol = $this->totalColumn();

        return (float) DB::connection('tenant')
            ->table('purchase_orders')
            ->whereBetween($dateCol, [$startDate, $endDate])
            ->where('status', 'received')
            ->sum($totalCol);
    }

    public function getPeriodSummary(string $startDate, string $endDate): array
    {
        $empty = [
            'received_count' => 0,
            'received_total' => 0.0,
            'draft_count' => 0,
            'open_count' => 0,
            'cancelled_count' => 0,
            'by_status' => [],
        ];

        if (! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return $empty;
        }

        $dateCol = $this->periodDateColumn();
        $totalCol = $this->totalColumn();
        $dateExpr = $dateCol === 'created_at'
            ? 'created_at'
            : "COALESCE({$dateCol}, created_at)";

        $rows = DB::connection('tenant')
            ->table('purchase_orders')
            ->whereRaw("{$dateExpr}::date BETWEEN ? AND ?", [$startDate, $endDate])
            ->select('status')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw("COALESCE(SUM({$totalCol}), 0) as total_sum")
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[(string) $r->status] = [
                'count' => (int) $r->cnt,
                'total' => (float) $r->total_sum,
            ];
        }

        $openStatuses = ['pending_validation', 'validated', 'ordered', 'partially_received'];
        $openCount = 0;
        foreach ($openStatuses as $st) {
            $openCount += (int) ($byStatus[$st]['count'] ?? 0);
        }

        return [
            'received_count' => (int) ($byStatus['received']['count'] ?? 0),
            'received_total' => (float) ($byStatus['received']['total'] ?? 0),
            'draft_count' => (int) ($byStatus['draft']['count'] ?? 0),
            'open_count' => $openCount,
            'cancelled_count' => (int) ($byStatus['cancelled']['count'] ?? 0),
            'by_status' => $byStatus,
        ];
    }

    private function periodDateColumn(): string
    {
        if (Schema::connection('tenant')->hasColumn('purchase_orders', 'ordered_at')) {
            return 'ordered_at';
        }

        return 'created_at';
    }

    private function totalColumn(): string
    {
        return 'total_ht';
    }
}
