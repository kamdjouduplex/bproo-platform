<?php

namespace Pressing\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Pressing\Models\PressingDelivery;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingOrderItem;
use Pressing\Models\PressingPayment;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingProfile;

class PressingDashboardService
{
    public function metrics(?string $profile = null): array
    {
        $profile = $profile ?? PressingProfile::current();
        $base = $this->baseOpsMetrics();

        $base['profile'] = $profile;
        $base['profile_label'] = PressingProfile::label($profile);
        $base['show_finance'] = PressingProfile::canSeeFinance($profile);
        $base['show_full_ops'] = PressingProfile::canSeeFullOps($profile);

        if ($profile === PressingProfile::DRIVER) {
            return array_merge($base, $this->driverMetrics(), [
                'show_finance' => false,
                'show_full_ops' => false,
            ]);
        }

        if ($profile === PressingProfile::PRODUCTION) {
            return array_merge($base, [
                'show_finance' => false,
            ]);
        }

        return $base;
    }

    public function revenueLast7Days(): array
    {
        $agenceId = PressingAgenceContext::scopedAgenceId();
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('D'),
                'is_today' => $day->isToday(),
                'total' => (float) $this->paymentsQuery($agenceId)
                    ->whereBetween('paid_at', [$day->copy(), $day->copy()->endOfDay()])
                    ->sum('amount'),
            ];
        }

        $max = max(1, ...array_column($days, 'total'));
        foreach ($days as &$day) {
            $day['bar_pct'] = round(($day['total'] / $max) * 100, 1);
        }

        return $days;
    }

    public function recentOrders(int $limit = 8): Collection
    {
        return $this->ordersQuery(PressingAgenceContext::scopedAgenceId())
            ->with(['client', 'agence', 'currentStage'])
            ->latest('received_at')
            ->limit($limit)
            ->get();
    }

    public function overdueList(int $limit = 6): Collection
    {
        return $this->ordersQuery(PressingAgenceContext::scopedAgenceId())
            ->with(['client', 'agence'])
            ->whereIn('status', ['open', 'ready'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit($limit)
            ->get();
    }

    public function waitingDeliveries(int $limit = 10, bool $mineOnly = false): Collection
    {
        $userId = Auth::guard('tenant')->id();
        $agenceId = PressingAgenceContext::scopedAgenceId();

        return PressingDelivery::query()
            ->with(['order.client', 'driver'])
            ->whereIn('status', ['pending', 'in_transit'])
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->when($mineOnly && $userId, function ($q) use ($userId) {
                $q->where(function ($inner) use ($userId) {
                    $inner->where('driver_user_id', $userId)
                        ->orWhere(function ($agency) {
                            $agency->where('type', 'agence')->whereNull('driver_user_id');
                        });
                });
            })
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function baseOpsMetrics(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth();
        $agenceId = PressingAgenceContext::scopedAgenceId();

        $ordersToday = $this->ordersQuery($agenceId)
            ->whereBetween('received_at', [$todayStart, $todayEnd])
            ->count();

        $itemsReceivedToday = PressingOrderItem::query()
            ->whereHas('order', function ($q) use ($todayStart, $todayEnd, $agenceId) {
                $q->whereBetween('received_at', [$todayStart, $todayEnd]);
                PressingAgenceContext::applyAgenceScope($q, 'agence_id', $agenceId);
            })
            ->sum('quantity');

        $deliveredToday = $this->ordersQuery($agenceId)
            ->where('status', 'delivered')
            ->whereBetween('updated_at', [$todayStart, $todayEnd])
            ->count();

        $pendingOrders = $this->ordersQuery($agenceId)
            ->whereIn('status', ['open', 'ready'])
            ->count();

        $overdueOrders = $this->ordersQuery($agenceId)
            ->whereIn('status', ['open', 'ready'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $readyOrders = $this->ordersQuery($agenceId)
            ->where('status', 'ready')
            ->count();

        $revenueToday = (float) $this->paymentsQuery($agenceId)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $revenueMonth = (float) $this->paymentsQuery($agenceId)
            ->whereBetween('paid_at', [$monthStart, $todayEnd])
            ->sum('amount');

        $balanceDue = (float) $this->ordersQuery($agenceId)
            ->whereIn('status', ['open', 'ready', 'delivered'])
            ->sum('balance');

        $waitingDeliveries = PressingDelivery::query()
            ->whereIn('status', ['pending', 'in_transit'])
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->count();

        return [
            'orders_today' => $ordersToday,
            'items_received_today' => (int) $itemsReceivedToday,
            'delivered_today' => $deliveredToday,
            'pending_orders' => $pendingOrders,
            'overdue_orders' => $overdueOrders,
            'ready_orders' => $readyOrders,
            'waiting_deliveries' => $waitingDeliveries,
            'revenue_today' => $revenueToday,
            'revenue_month' => $revenueMonth,
            'balance_due' => $balanceDue,
            'agence_scope_label' => PressingAgenceContext::scopeLabel(),
            'agence_id' => $agenceId,
            'can_view_all_agences' => PressingAgenceContext::canViewAllAgences(),
        ];
    }

    private function driverMetrics(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $userId = Auth::guard('tenant')->id();
        $agenceId = PressingAgenceContext::scopedAgenceId();

        $mine = PressingDelivery::query()
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->when($userId, fn ($q) => $q->where('driver_user_id', $userId));

        $waitingMine = (clone $mine)->whereIn('status', ['pending', 'in_transit'])->count();
        $inTransitMine = (clone $mine)->where('status', 'in_transit')->count();
        $deliveredMineToday = (clone $mine)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$todayStart, $todayEnd])
            ->count();

        $waitingAll = PressingDelivery::query()
            ->whereIn('status', ['pending', 'in_transit'])
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->count();

        $domicileWaiting = PressingDelivery::query()
            ->where('type', 'domicile')
            ->whereIn('status', ['pending', 'in_transit'])
            ->when($agenceId, fn ($q) => $q->where('agence_id', $agenceId))
            ->count();

        return [
            'waiting_deliveries' => $waitingAll,
            'waiting_mine' => $waitingMine,
            'in_transit_mine' => $inTransitMine,
            'delivered_mine_today' => $deliveredMineToday,
            'domicile_waiting' => $domicileWaiting,
            // Hide finance noise for drivers
            'revenue_today' => 0,
            'revenue_month' => 0,
            'balance_due' => 0,
        ];
    }

    private function ordersQuery(?int $agenceId = null): Builder
    {
        $query = PressingOrder::query();
        PressingAgenceContext::applyAgenceScope($query, 'agence_id', $agenceId);

        return $query;
    }

    private function paymentsQuery(?int $agenceId = null): Builder
    {
        $query = PressingPayment::query();
        PressingAgenceContext::applyAgenceScope($query, 'agence_id', $agenceId);

        return $query;
    }
}
