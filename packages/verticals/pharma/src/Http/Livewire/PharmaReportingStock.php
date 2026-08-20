<?php

namespace Pharma\Http\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingStock extends Component
{
    use InteractsWithPharmaReporting;

    #[Url(as: 'view', except: 'stockouts')]
    public string $viewBy = 'stockouts';

    #[Url(as: 'days', except: 90)]
    public int $horizonDays = 90;

    public function mount(): void
    {
        $this->bootReporting();
        $this->normalizeFilters();
    }

    public function updatedViewBy(): void
    {
        $this->normalizeFilters();
    }

    public function updatedHorizonDays(): void
    {
        $this->normalizeFilters();
    }

    public function resetFilters(): void
    {
        $this->viewBy = 'stockouts';
        $this->horizonDays = 90;
        $this->applyPeriodPreset('this_month');
    }

    public function exportUrl(string $format): string
    {
        $route = $format === 'pdf'
            ? 'tenant.pharma-reporting.stock.print'
            : 'tenant.pharma-reporting.stock.excel';

        return route($route, array_filter([
            ...$this->periodQuery(),
            'view' => $this->viewBy !== 'stockouts' ? $this->viewBy : null,
            'days' => $this->horizonDays !== 90 ? $this->horizonDays : null,
        ]));
    }

    public function render()
    {
        $service = app(PharmaReportingService::class);
        [$from, $to] = $this->currentRange();
        $kpis = $service->kpis($from, $to);
        $days = $this->horizonDays;

        $rows = match ($this->viewBy) {
            'low' => $service->lowStockItems(200),
            'expiring' => $service->expiringBatches($days, 200),
            'dead' => $service->deadStockItems($days, 200),
            default => $service->stockoutItems(200),
        };

        $cards = [
            [
                'label' => 'Valeur du stock',
                'value' => fmt_money($kpis['stock_value']).' '.$this->currency(),
                'trend' => null,
                'tone' => 'teal',
                'icon' => 'package',
            ],
            [
                'label' => 'Ruptures',
                'value' => fmt_num($service->stockoutCount(), 0),
                'trend' => null,
                'tone' => 'rose',
                'icon' => 'alert',
            ],
            [
                'label' => 'Stock bas',
                'value' => fmt_num($service->lowStockCount(), 0),
                'trend' => null,
                'tone' => 'amber',
                'icon' => 'chart',
            ],
            [
                'label' => 'Lots < '.$days.' j.',
                'value' => fmt_num($service->expiringBatchesCount($days), 0),
                'trend' => null,
                'tone' => 'amber',
                'icon' => 'document',
            ],
            [
                'label' => 'Stock dormant',
                'value' => fmt_num($service->deadStockCount($days), 0),
                'trend' => null,
                'tone' => 'violet',
                'icon' => 'package',
            ],
            [
                'label' => 'CA de la période',
                'value' => fmt_money($kpis['ca']).' '.$this->currency(),
                'trend' => null,
                'tone' => 'green',
                'icon' => 'shopping-bag',
            ],
        ];

        $titles = [
            'stockouts' => 'Ruptures',
            'low' => 'Stock bas',
            'expiring' => 'Lots bientôt périmés (< '.$days.' j.)',
            'dead' => 'Stock dormant ('.$days.' j. sans vente)',
        ];

        return view('pharma::livewire.reporting.stock', [
            'activePage' => 'stock',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'cards' => $cards,
            'rows' => $rows,
            'tableTitle' => $titles[$this->viewBy] ?? 'Stock',
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Stock',
            'Ruptures, péremption et valeur'
        ));
    }

    private function normalizeFilters(): void
    {
        if (! in_array($this->viewBy, ['stockouts', 'low', 'expiring', 'dead'], true)) {
            $this->viewBy = 'stockouts';
        }
        $this->horizonDays = (int) $this->horizonDays;
        if (! in_array($this->horizonDays, [30, 60, 90], true)) {
            $this->horizonDays = 90;
        }
    }
}
