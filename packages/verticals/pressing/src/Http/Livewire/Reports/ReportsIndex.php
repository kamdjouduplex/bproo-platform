<?php

namespace Pressing\Http\Livewire\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Livewire\Component;
use Pressing\Concerns\AuthorizesPressingActions;
use Pressing\Models\PressingDelivery;
use Pressing\Models\PressingOrder;
use Pressing\Models\PressingPayment;
use Pressing\Support\PressingWorkflow;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsIndex extends Component
{
    use AuthorizesPressingActions;

    public string $tab = 'general';

    public string $period = 'month';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->authorizePressingAction('pressing_orders.view');
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['general', 'production', 'finances'], true) ? $tab : 'general';
    }

    public function updatedPeriod(): void
    {
        match ($this->period) {
            'day' => [$this->from, $this->to] = [now()->toDateString(), now()->toDateString()],
            'week' => [$this->from, $this->to] = [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'year' => [$this->from, $this->to] = [now()->startOfYear()->toDateString(), now()->toDateString()],
            default => [$this->from, $this->to] = [now()->startOfMonth()->toDateString(), now()->toDateString()],
        };
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizePressingAction('pressing_orders.view');

        $orders = $this->ordersQuery()->with(['client', 'agence'])->get();

        return Response::streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'client', 'agence', 'status', 'total', 'paid', 'balance', 'received_at']);
            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->number,
                    $order->client?->full_name,
                    $order->agence?->name,
                    $order->status,
                    $order->total,
                    $order->amount_paid,
                    $order->balance,
                    optional($order->received_at)->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, 'pressing-rapport-'.$this->from.'-'.$this->to.'.csv');
    }

    private function range(): array
    {
        return [
            Carbon::parse($this->from)->startOfDay(),
            Carbon::parse($this->to)->endOfDay(),
        ];
    }

    private function previousRange(): array
    {
        [$from, $to] = $this->range();
        $days = max(1, $from->diffInDays($to) + 1);

        return [
            $from->copy()->subDays($days),
            $to->copy()->subDays($days),
        ];
    }

    private function ordersQuery(?Carbon $from = null, ?Carbon $to = null)
    {
        [$f, $t] = $from && $to ? [$from, $to] : $this->range();

        return PressingOrder::query()->whereBetween('received_at', [$f, $t]);
    }

    private function paymentsQuery(?Carbon $from = null, ?Carbon $to = null)
    {
        [$f, $t] = $from && $to ? [$from, $to] : $this->range();

        return PressingPayment::query()->whereBetween('paid_at', [$f, $t]);
    }

    private function pctChange(float $current, float $previous): ?float
    {
        if ($previous <= 0 && $current <= 0) {
            return null;
        }
        if ($previous <= 0) {
            return 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function periodLabel(): string
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        if ($from->isSameDay($to)) {
            return $from->translatedFormat('d F Y');
        }
        if ($from->isSameMonth($to) && $from->isSameYear($to) && $from->day === 1 && $to->day >= now()->day) {
            return $from->translatedFormat('F Y');
        }

        return $from->format('d/m/Y').' → '.$to->format('d/m/Y');
    }

    public function render()
    {
        $this->authorizePressingAction('pressing_orders.view');

        [$from, $to] = $this->range();
        [$prevFrom, $prevTo] = $this->previousRange();

        $ordersCount = (clone $this->ordersQuery())->count();
        $ordersTotal = (float) (clone $this->ordersQuery())->sum('total');
        $paymentsTotal = (float) (clone $this->paymentsQuery())->sum('amount');
        $balanceDue = (float) (clone $this->ordersQuery())->sum('balance');
        $delivered = (clone $this->ordersQuery())->where('status', 'delivered')->count();
        $ready = PressingOrder::query()->where('status', 'ready')->count();
        $open = PressingOrder::query()->where('status', 'open')->count();
        $overdue = PressingOrder::query()
            ->whereIn('status', ['open', 'ready'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $creditPending = PressingOrder::query()->where('credit_status', 'pending')->count();
        $avgTicket = $ordersCount > 0 ? round($ordersTotal / $ordersCount) : 0;
        $collectionRate = $ordersTotal > 0 ? round(($paymentsTotal / $ordersTotal) * 100, 1) : null;

        $prevOrdersCount = (clone $this->ordersQuery($prevFrom, $prevTo))->count();
        $prevOrdersTotal = (float) (clone $this->ordersQuery($prevFrom, $prevTo))->sum('total');
        $prevPayments = (float) (clone $this->paymentsQuery($prevFrom, $prevTo))->sum('amount');
        $prevDelivered = (clone $this->ordersQuery($prevFrom, $prevTo))->where('status', 'delivered')->count();

        $kpis = [
            [
                'key' => 'ca',
                'label' => __('CA commandes'),
                'value' => number_format($ordersTotal, 0, ',', ' '),
                'suffix' => 'FCFA',
                'trend' => $this->pctChange($ordersTotal, $prevOrdersTotal),
                'tone' => 'indigo',
                'icon' => 'chart',
            ],
            [
                'key' => 'cash',
                'label' => __('Encaissements'),
                'value' => number_format($paymentsTotal, 0, ',', ' '),
                'suffix' => 'FCFA',
                'trend' => $this->pctChange($paymentsTotal, $prevPayments),
                'tone' => 'teal',
                'icon' => 'cash',
            ],
            [
                'key' => 'orders',
                'label' => __('Commandes'),
                'value' => (string) $ordersCount,
                'suffix' => '',
                'trend' => $this->pctChange((float) $ordersCount, (float) $prevOrdersCount),
                'tone' => 'sky',
                'icon' => 'orders',
            ],
            [
                'key' => 'delivered',
                'label' => __('Livrées'),
                'value' => (string) $delivered,
                'suffix' => '',
                'trend' => $this->pctChange((float) $delivered, (float) $prevDelivered),
                'tone' => 'green',
                'icon' => 'truck',
            ],
            [
                'key' => 'due',
                'label' => __('Reste dû'),
                'value' => number_format($balanceDue, 0, ',', ' '),
                'suffix' => 'FCFA',
                'trend' => null,
                'tone' => $balanceDue > 0 ? 'amber' : 'green',
                'icon' => 'due',
            ],
            [
                'key' => 'avg',
                'label' => __('Ticket moyen'),
                'value' => number_format($avgTicket, 0, ',', ' '),
                'suffix' => 'FCFA',
                'trend' => null,
                'tone' => 'violet',
                'icon' => 'ticket',
            ],
        ];

        // Daily payment chart for current range (cap 31 points)
        $chart = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $span = min(31, $cursor->diffInDays($end) + 1);
        if ($cursor->diffInDays($end) + 1 > 31) {
            $cursor = $end->copy()->subDays(30);
        }
        for ($i = 0; $i < $span; $i++) {
            $day = $cursor->copy()->addDays($i);
            $chart[] = [
                'label' => $day->format('d/m'),
                'is_today' => $day->isToday(),
                'total' => (float) PressingPayment::query()
                    ->whereBetween('paid_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                    ->sum('amount'),
            ];
        }
        $maxChart = max(1, ...array_column($chart, 'total'));
        foreach ($chart as &$row) {
            $row['pct'] = round(($row['total'] / $maxChart) * 100, 1);
        }
        unset($row);

        // Production pipeline (open pipeline, not limited to period)
        $stageNames = array_merge(
            [PressingWorkflow::STAGE_TRI],
            PressingWorkflow::kanbanStageNames(),
            [PressingWorkflow::STAGE_PRET]
        );
        $pipeline = [];
        $pipelineTotal = 0;
        foreach ($stageNames as $name) {
            $stage = PressingWorkflow::stageByName($name);
            $count = $stage
                ? PressingOrder::query()->where('current_stage_id', $stage->id)->whereIn('status', ['open', 'ready'])->count()
                : 0;
            $pipeline[] = ['name' => $name, 'count' => $count];
            $pipelineTotal += $count;
        }

        $paymentsByMethod = PressingPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, count(*) as c, sum(amount) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method' => PressingPayment::METHODS[$r->method] ?? $r->method,
                'count' => (int) $r->c,
                'total' => (float) $r->total,
            ]);

        $deliveriesDone = PressingDelivery::query()
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [$from, $to])
            ->count();
        $deliveriesWaiting = PressingDelivery::query()
            ->whereIn('status', ['pending', 'in_transit'])
            ->count();

        $health = [
            [
                'label' => __('Taux d’encaissement'),
                'value' => $collectionRate !== null ? $collectionRate.'%' : '—',
                'status' => $collectionRate === null ? 'neutral' : ($collectionRate >= 80 ? 'good' : ($collectionRate >= 50 ? 'warn' : 'danger')),
                'hint' => __('Encaissé / CA période'),
            ],
            [
                'label' => __('Commandes prêtes'),
                'value' => (string) $ready,
                'status' => $ready > 0 ? 'warn' : 'good',
                'hint' => __('En attente de remise'),
            ],
            [
                'label' => __('En retard'),
                'value' => (string) $overdue,
                'status' => $overdue > 0 ? 'danger' : 'good',
                'hint' => __('Délai dépassé'),
            ],
            [
                'label' => __('Crédits en attente'),
                'value' => (string) $creditPending,
                'status' => $creditPending > 0 ? 'warn' : 'good',
                'hint' => __('Validation requise'),
            ],
            [
                'label' => __('Livraisons en attente'),
                'value' => (string) $deliveriesWaiting,
                'status' => $deliveriesWaiting > 5 ? 'warn' : 'good',
                'hint' => __('Pending + in transit'),
            ],
        ];

        $tenantCode = request()->query('tenant')
            ?? session('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;

        return view('pressing::livewire.reports.index', [
            'kpis' => $kpis,
            'chart' => $chart,
            'pipeline' => $pipeline,
            'pipelineTotal' => $pipelineTotal,
            'health' => $health,
            'paymentsByMethod' => $paymentsByMethod,
            'orders' => (clone $this->ordersQuery())->with(['client', 'agence', 'currentStage'])->latest('received_at')->limit(40)->get(),
            'periodLabel' => $this->periodLabel(),
            'ordersCount' => $ordersCount,
            'ordersTotal' => $ordersTotal,
            'paymentsTotal' => $paymentsTotal,
            'balanceDue' => $balanceDue,
            'delivered' => $delivered,
            'open' => $open,
            'ready' => $ready,
            'overdue' => $overdue,
            'deliveriesDone' => $deliveriesDone,
            'tenantCode' => $tenantCode,
            'updatedAt' => now()->format('H:i'),
        ])->layout('layouts.app', [
            'title' => __('Rapports et analyses'),
            'subtitle' => $this->periodLabel(),
        ]);
    }
}
