<?php

namespace Pharma\Http\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingProfitability extends Component
{
    use InteractsWithPharmaReporting;

    #[Url(as: 'view', except: 'category')]
    public string $viewBy = 'category';

    public function mount(): void
    {
        $this->bootReporting();
        if (! in_array($this->viewBy, ['category', 'product'], true)) {
            $this->viewBy = 'category';
        }
    }

    public function updatedViewBy(): void
    {
        if (! in_array($this->viewBy, ['category', 'product'], true)) {
            $this->viewBy = 'category';
        }
    }

    public function resetFilters(): void
    {
        $this->viewBy = 'category';
        $this->applyPeriodPreset('this_month');
    }

    public function exportUrl(string $format): string
    {
        $route = $format === 'pdf'
            ? 'tenant.pharma-reporting.profitability.print'
            : 'tenant.pharma-reporting.profitability.excel';

        return route($route, array_filter([
            ...$this->periodQuery(),
            'view' => $this->viewBy !== 'category' ? $this->viewBy : null,
        ]));
    }

    public function render()
    {
        $service = app(PharmaReportingService::class);
        [$from, $to] = $this->currentRange();
        [$prevFrom, $prevTo] = $service->previousRange($from, $to);
        $kpis = $service->kpis($from, $to);
        $prev = $service->kpis($prevFrom, $prevTo);
        $byCategory = $this->viewBy === 'category' ? $service->profitabilityByCategory($from, $to) : [];
        $byProduct = $this->viewBy === 'product' ? $service->profitabilityByProduct($from, $to, 200) : [];

        $cards = [
            [
                'label' => 'Chiffre d’affaires',
                'value' => fmt_money($kpis['ca']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['ca'], $prev['ca']),
                'tone' => 'teal',
                'icon' => 'shopping-bag',
            ],
            [
                'label' => 'Coût des ventes',
                'value' => fmt_money($kpis['cogs']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['cogs'], $prev['cogs']),
                'tone' => 'amber',
                'icon' => 'package',
                'invert_trend' => true,
            ],
            [
                'label' => 'Marge brute',
                'value' => fmt_money($kpis['margin']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['margin'], $prev['margin']),
                'tone' => 'green',
                'icon' => 'banknotes',
            ],
            [
                'label' => 'Taux de marge',
                'value' => fmt_num($kpis['margin_rate'], 1).' %',
                'trend' => $service->trendPts($kpis['margin_rate'], $prev['margin_rate']),
                'tone' => 'blue',
                'icon' => 'chart',
                'pts' => true,
            ],
            [
                'label' => 'Pertes confirmées',
                'value' => fmt_money($kpis['losses']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['losses'], $prev['losses']),
                'tone' => 'rose',
                'icon' => 'alert',
                'invert_trend' => true,
            ],
            [
                'label' => 'Bénéfice brut',
                'value' => fmt_money($kpis['benefit']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['benefit'], $prev['benefit']),
                'tone' => 'green',
                'icon' => 'wallet',
            ],
        ];

        return view('pharma::livewire.reporting.profitability', [
            'activePage' => 'profitability',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'cards' => $cards,
            'byCategory' => $byCategory,
            'byProduct' => $byProduct,
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Rentabilité',
            $service->periodLabel($from, $to)
        ));
    }
}
