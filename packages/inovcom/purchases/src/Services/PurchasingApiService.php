<?php

namespace InovCom\Purchases\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Kernel\Contracts\PurchasingApi;
use InovCom\Purchases\Models\PurchaseOrder;

class PurchasingApiService implements PurchasingApi
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
            ->where('order_number', $number)
            ->first();
    }

    public function purchaseOrderExists(int $id): bool
    {
        return $this->findPurchaseOrder($id) !== null;
    }

    public function getTotal(int $purchaseOrderId): float
    {
        $order = $this->findPurchaseOrder($purchaseOrderId);

        return $order ? (float) $order->total : 0.0;
    }

    public function getPeriodReceivedTotal(string $startDate, string $endDate): float
    {
        if (! Schema::connection('tenant')->hasTable('purchase_orders')) {
            return 0.0;
        }

        return (float) DB::connection('tenant')
            ->table('purchase_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', PurchasesService::STATUS_RECEIVED)
            ->sum('total');
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

        $rows = DB::connection('tenant')
            ->table('purchase_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->select('status')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sum')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[(string) $r->status] = [
                'count' => (int) $r->cnt,
                'total' => (float) $r->total_sum,
            ];
        }

        $openStatuses = [
            PurchasesService::STATUS_CONFIRMED,
            PurchasesService::STATUS_PARTIAL,
            PurchasesService::STATUS_SENT_LEGACY,
        ];
        $openCount = 0;
        foreach ($openStatuses as $st) {
            $openCount += (int) ($byStatus[$st]['count'] ?? 0);
        }

        return [
            'received_count' => (int) ($byStatus[PurchasesService::STATUS_RECEIVED]['count'] ?? 0),
            'received_total' => (float) ($byStatus[PurchasesService::STATUS_RECEIVED]['total'] ?? 0),
            'draft_count' => (int) ($byStatus[PurchasesService::STATUS_DRAFT]['count'] ?? 0),
            'open_count' => $openCount,
            'cancelled_count' => (int) ($byStatus[PurchasesService::STATUS_CANCELLED]['count'] ?? 0),
            'by_status' => $byStatus,
        ];
    }
}
