<?php

namespace Pharma\Http\Livewire;

use Livewire\Component;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingAlerts extends Component
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
        $alerts = $service->alerts();
        $stockouts = $service->stockoutItems(60);
        $low = $service->lowStockItems(60);
        $expiring = $service->expiringBatches(90, 60);
        $dead = $service->deadStockItems(90, 60);

        $cards = [
            [
                'label' => 'Alertes actives',
                'value' => fmt_num(count($alerts), 0),
                'trend' => null,
                'tone' => 'rose',
                'icon' => 'alert',
            ],
            [
                'label' => 'Ruptures',
                'value' => fmt_num($service->stockoutCount(), 0),
                'trend' => null,
                'tone' => 'rose',
                'icon' => 'package',
            ],
            [
                'label' => 'Stock bas',
                'value' => fmt_num($service->lowStockCount(), 0),
                'trend' => null,
                'tone' => 'amber',
                'icon' => 'chart',
            ],
            [
                'label' => 'Lots < 90 j.',
                'value' => fmt_num($service->expiringBatchesCount(90), 0),
                'trend' => null,
                'tone' => 'amber',
                'icon' => 'document',
            ],
            [
                'label' => 'Stock dormant',
                'value' => fmt_num($service->deadStockCount(90), 0),
                'trend' => null,
                'tone' => 'violet',
                'icon' => 'package',
            ],
            [
                'label' => 'Créances en retard',
                'value' => fmt_num($service->overdueDebtsCount(), 0),
                'trend' => null,
                'tone' => 'blue',
                'icon' => 'wallet',
            ],
        ];

        return view('pharma::livewire.reporting.alerts', [
            'activePage' => 'alerts',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'cards' => $cards,
            'alerts' => $alerts,
            'stockouts' => $stockouts,
            'low' => $low,
            'expiring' => $expiring,
            'dead' => $dead,
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Alertes',
            'Points de vigilance de l’officine'
        ));
    }
}
