<?php

namespace Pharma\Http\Livewire;

use Livewire\Component;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingPurchases extends Component
{
    use InteractsWithPharmaReporting;

    public function mount(): void
    {
        $this->bootReporting();
    }

    public function render()
    {
        $service = app(PharmaReportingService::class);
        [$from, $to] = $this->currentRange();
        [$prevFrom, $prevTo] = $service->previousRange($from, $to);
        $kpis = $service->kpis($from, $to);
        $prev = $service->kpis($prevFrom, $prevTo);
        $orders = $service->purchaseOrders($from, $to, 80);
        $byProvider = $service->purchasesByProvider($from, $to, 12);

        $open = collect($orders)->whereIn('status', ['confirmed', 'partial', 'sent'])->count();
        $received = collect($orders)->where('status', 'received')->count();

        $cards = [
            [
                'label' => 'Achats (réceptions)',
                'value' => fmt_money($kpis['purchases']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['purchases'], $prev['purchases']),
                'tone' => 'teal',
                'icon' => 'package',
            ],
            [
                'label' => 'Commandes',
                'value' => fmt_num(count($orders), 0),
                'trend' => null,
                'tone' => 'blue',
                'icon' => 'document',
            ],
            [
                'label' => 'Réceptionnées',
                'value' => fmt_num($received, 0),
                'trend' => null,
                'tone' => 'green',
                'icon' => 'receipt',
            ],
            [
                'label' => 'En cours',
                'value' => fmt_num($open, 0),
                'trend' => null,
                'tone' => 'amber',
                'icon' => 'chart',
            ],
            [
                'label' => 'Fournisseurs actifs',
                'value' => fmt_num(count($byProvider), 0),
                'trend' => null,
                'tone' => 'violet',
                'icon' => 'users',
            ],
            [
                'label' => 'CA ventes',
                'value' => fmt_money($kpis['ca']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['ca'], $prev['ca']),
                'tone' => 'green',
                'icon' => 'shopping-bag',
            ],
        ];

        return view('pharma::livewire.reporting.purchases', [
            'activePage' => 'purchases',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'cards' => $cards,
            'orders' => $orders,
            'byProvider' => $byProvider,
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Achats & fournisseurs',
            $service->periodLabel($from, $to)
        ));
    }
}
