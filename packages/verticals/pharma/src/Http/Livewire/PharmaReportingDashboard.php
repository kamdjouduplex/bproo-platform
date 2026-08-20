<?php

namespace Pharma\Http\Livewire;

use Livewire\Component;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingDashboard extends Component
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
        $series = $service->dailyCa($from, $to);
        $categories = $service->salesByCategory($from, $to);
        $payments = $service->paymentsBreakdown($from, $to);
        $topProducts = $service->topProducts($from, $to, 5);
        $alerts = $service->alerts();

        $marginRatePrev = $prev['margin_rate'];
        $marginDrop = $kpis['margin_rate'] < $marginRatePrev - 1 && $prev['ca'] > 0;
        if ($marginDrop) {
            array_unshift($alerts, [
                'key' => 'margin',
                'tone' => 'amber',
                'icon' => 'chart',
                'title' => 'Baisse de marge',
                'value' => fmt_num($kpis['margin_rate'], 1).' %',
                'hint' => ($service->trendPts($kpis['margin_rate'], $marginRatePrev) ?? 0).' pts vs période préc.',
                'route' => 'tenant.pharma-reporting.profitability',
            ]);
        }

        $headline = [
            [
                'key' => 'ca',
                'label' => 'CA vente directe',
                'value' => $kpis['ca'],
                'money' => true,
                'trend' => $service->trendPct($kpis['ca'], $prev['ca']),
                'tone' => 'teal',
                'icon' => 'shopping-bag',
            ],
            [
                'key' => 'expenses',
                'label' => 'Dépenses',
                'value' => $kpis['expenses'],
                'money' => true,
                'trend' => $service->trendPct($kpis['expenses'], $prev['expenses']),
                'tone' => 'rose',
                'icon' => 'wallet',
                'invert_trend' => true,
            ],
            [
                'key' => 'losses',
                'label' => 'Pertes',
                'value' => $kpis['losses'],
                'money' => true,
                'trend' => $service->trendPct($kpis['losses'], $prev['losses']),
                'tone' => 'amber',
                'icon' => 'alert',
                'invert_trend' => true,
            ],
            [
                'key' => 'purchases',
                'label' => 'Achats',
                'value' => $kpis['purchases'],
                'money' => true,
                'trend' => $service->trendPct($kpis['purchases'], $prev['purchases']),
                'tone' => 'blue',
                'icon' => 'package',
            ],
            [
                'key' => 'benefit',
                'label' => 'Bénéfice brut',
                'value' => $kpis['benefit'],
                'money' => true,
                'trend' => $service->trendPct($kpis['benefit'], $prev['benefit']),
                'tone' => 'green',
                'icon' => 'banknotes',
            ],
        ];

        $indicators = [
            ['label' => 'Nombre de ventes', 'value' => fmt_num($kpis['sales_count'], 0), 'hint' => null],
            ['label' => 'Panier moyen', 'value' => fmt_money($kpis['avg_basket']).' '.$this->currency(), 'hint' => null],
            ['label' => 'Produits vendus', 'value' => fmt_num($kpis['qty_sold'], 0), 'hint' => null],
            [
                'label' => 'Taux de marge',
                'value' => fmt_num($kpis['margin_rate'], 1).' %',
                'hint' => $kpis['margin_rate'] >= 30 ? 'Bonne' : ($kpis['margin_rate'] >= 15 ? 'Correcte' : 'Faible'),
                'hint_tone' => $kpis['margin_rate'] >= 30 ? 'good' : ($kpis['margin_rate'] >= 15 ? 'warn' : 'bad'),
            ],
            ['label' => 'Valeur du stock', 'value' => fmt_money($kpis['stock_value']).' '.$this->currency(), 'hint' => null],
            ['label' => 'Créances clients', 'value' => fmt_money($kpis['overdue_total']).' '.$this->currency(), 'hint' => null],
        ];

        return view('pharma::livewire.reporting.dashboard', [
            'activePage' => 'dashboard',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'headline' => $headline,
            'kpis' => $kpis,
            'chart' => $this->chartBars($series),
            'categories' => $categories,
            'categoryGradient' => $this->conicGradient($categories),
            'payments' => $payments,
            'paymentGradient' => $this->conicGradient($payments),
            'topProducts' => $topProducts,
            'alerts' => $alerts,
            'indicators' => $indicators,
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Rapport Pharmacie',
            'Tableau de bord · '.$service->periodLabel($from, $to)
        ));
    }

    /**
     * Histogramme journalier. Si un jour écrase les autres, l’échelle
     * s’aligne sur le 2e plus haut montant pour garder le reste lisible.
     *
     * @param  array<int, array{total: float, label?: string, short?: string, is_today?: bool}>  $series
     * @return array{
     *     bars: list<array{pct: float, total: float, label: string, short: string, clipped: bool, is_today: bool}>,
     *     ticks: list<float>,
     *     avg: float,
     *     best: ?array{label: string, total: float},
     *     clipped: bool
     * }
     */
    private function chartBars(array $series): array
    {
        if ($series === []) {
            return ['bars' => [], 'ticks' => [], 'avg' => 0, 'best' => null, 'clipped' => false];
        }

        $values = array_map(fn ($point) => (float) $point['total'], $series);
        $max = max($values) ?: 1;
        $ranked = $values;
        rsort($ranked, SORT_NUMERIC);
        $second = (float) ($ranked[1] ?? 0);
        $scale = ($second > 0 && $max > $second * 3) ? $second * 1.25 : $max;
        if ($scale <= 0) {
            $scale = 1;
        }

        $bars = [];
        $best = null;
        $sum = 0.0;
        foreach ($series as $point) {
            $total = (float) $point['total'];
            $sum += $total;
            $clipped = $total > $scale * 1.001;
            $bars[] = [
                'pct' => round(min(100, ($total / $scale) * 100), 1),
                'total' => $total,
                'label' => $point['label'] ?? $point['short'] ?? '',
                'short' => $point['short'] ?? '',
                'clipped' => $clipped,
                'is_today' => (bool) ($point['is_today'] ?? false),
            ];
            if ($best === null || $total > $best['total']) {
                $best = [
                    'label' => $point['short'] ?? $point['label'] ?? '',
                    'total' => $total,
                ];
            }
        }

        return [
            'bars' => $bars,
            'ticks' => [$scale, $scale / 2, 0],
            'avg' => $sum / count($series),
            'best' => $best,
            'clipped' => $max > $scale,
        ];
    }
}
