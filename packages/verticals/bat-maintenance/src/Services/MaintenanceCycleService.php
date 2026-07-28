<?php

namespace InovCom\Maintenance\Services;

use Carbon\Carbon;
use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Maintenance\Models\MaintenanceOrder;
use InovCom\Maintenance\Support\MaintenanceCycle;

class MaintenanceCycleService
{
    public function summarize(MaintenanceContract $contract): array
    {
        $contract->loadMissing(['orders' => fn ($q) => $q->orderByDesc('reported_at')->limit(10)]);

        $upcoming = $contract->next_intervention_at
            ? Carbon::parse($contract->next_intervention_at)
            : null;

        $isOverdue = $upcoming && $upcoming->isPast() && $contract->status === 'active';

        $recentOrders = $contract->orders->map(fn (MaintenanceOrder $order) => [
            'id'          => $order->id,
            'code'        => $order->code,
            'title'       => $order->title,
            'type'        => $order->type,
            'status'      => $order->status,
            'reported_at' => $order->reported_at?->format('d/m/Y'),
            'due_at'      => $order->due_at?->format('d/m/Y H:i'),
        ]);

        return [
            'frequency_label'     => MaintenanceCycle::frequencyLabel($contract->intervention_frequency),
            'billing_label'       => MaintenanceCycle::billingCycles()[$contract->billing_cycle] ?? $contract->billing_cycle,
            'next_intervention'   => $upcoming?->format('d/m/Y'),
            'last_intervention'   => $contract->last_intervention_at?->format('d/m/Y'),
            'is_overdue'          => $isOverdue,
            'auto_generate'       => (bool) $contract->auto_generate_orders,
            'recent_orders'       => $recentOrders,
            'open_orders_count'   => $contract->orders()->whereNotIn('status', ['closed', 'cancelled'])->count(),
        ];
    }

    public function planPreventiveOrder(MaintenanceContract $contract, ?int $userId = null): MaintenanceOrder
    {
        if ($contract->status !== 'active') {
            throw new \RuntimeException(__('Le contrat doit être actif pour planifier une intervention.'));
        }

        if (!in_array($contract->type, ['preventive', 'full_service'], true)) {
            throw new \RuntimeException(__('Ce type de contrat ne génère pas d\'interventions préventives planifiées.'));
        }

        $reportedAt = $contract->next_intervention_at
            ? Carbon::parse($contract->next_intervention_at)->startOfDay()
            : now();

        $order = $this->createPreventiveOrder($contract, $reportedAt, $userId);

        $contract->update([
            'last_intervention_at' => $reportedAt->toDateString(),
            'next_intervention_at' => MaintenanceCycle::computeNextInterventionDate(
                $contract->fresh()->fill(['last_intervention_at' => $reportedAt])
            )?->toDateString(),
        ]);

        return $order;
    }

    public function createPreventiveOrder(
        MaintenanceContract $contract,
        ?Carbon $reportedAt = null,
        ?int $userId = null
    ): MaintenanceOrder {
        $reportedAt ??= now();

        $code = $this->nextOrderCode();

        $siteAddress = '';
        if ($contract->sites) {
            $first = collect($contract->sites)->first();
            $siteAddress = $first['address'] ?? '';
        }

        $dueAt = $contract->computeDueAt($reportedAt);

        return MaintenanceOrder::on('tenant')->create([
            'code'         => $code,
            'contract_id'  => $contract->id,
            'client_id'    => $contract->client_id,
            'title'        => __('Visite :frequency — :contract', [
                'frequency' => MaintenanceCycle::frequencyLabel($contract->intervention_frequency),
                'contract'  => $contract->code,
            ]),
            'description'  => __('Intervention planifiée selon le cycle :frequency.', [
                'frequency' => MaintenanceCycle::frequencyLabel($contract->intervention_frequency),
            ]),
            'type'         => 'preventive',
            'priority'     => 'normal',
            'status'       => 'open',
            'reported_at'  => $reportedAt,
            'due_at'       => $dueAt,
            'site_address' => $siteAddress,
            'reported_by'  => $userId
                ? (string) $userId
                : __('Système (planification automatique)'),
        ]);
    }

    public function shouldAutoGenerate(MaintenanceContract $contract): bool
    {
        if ($contract->status !== 'active' || !$contract->auto_generate_orders) {
            return false;
        }

        if (!in_array($contract->type, ['preventive', 'full_service'], true)) {
            return false;
        }

        if (!$contract->next_intervention_at) {
            return false;
        }

        if (Carbon::parse($contract->next_intervention_at)->isFuture()) {
            return false;
        }

        $periodStart = MaintenanceCycle::periodStartForFrequency(
            $contract->intervention_frequency ?? MaintenanceCycle::FREQ_MONTHLY
        );

        return !MaintenanceOrder::on('tenant')
            ->where('contract_id', $contract->id)
            ->where('type', 'preventive')
            ->where('created_at', '>=', $periodStart)
            ->exists();
    }

    private function nextOrderCode(): string
    {
        $max = MaintenanceOrder::on('tenant')
            ->where('code', 'like', 'OM%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 2))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'OM' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
