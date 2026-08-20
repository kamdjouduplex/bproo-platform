<?php

namespace Pharma\Http\Livewire;

use Livewire\Component;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingFinance extends Component
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
        $payments = $service->paymentsBreakdown($from, $to);
        $expenses = $service->expensesByCategory($from, $to);
        $debts = $service->debtsSnapshot($from, $to);

        $cards = [
            [
                'label' => 'CA encaissé (ventes)',
                'value' => fmt_money($kpis['ca']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['ca'], $prev['ca']),
                'tone' => 'teal',
                'icon' => 'shopping-bag',
            ],
            [
                'label' => 'Dépenses',
                'value' => fmt_money($kpis['expenses']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['expenses'], $prev['expenses']),
                'tone' => 'rose',
                'icon' => 'wallet',
                'invert_trend' => true,
            ],
            [
                'label' => 'Pertes',
                'value' => fmt_money($kpis['losses']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['losses'], $prev['losses']),
                'tone' => 'amber',
                'icon' => 'alert',
                'invert_trend' => true,
            ],
            [
                'label' => 'Bénéfice brut',
                'value' => fmt_money($kpis['benefit']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['benefit'], $prev['benefit']),
                'tone' => 'green',
                'icon' => 'banknotes',
            ],
            [
                'label' => 'Créances',
                'value' => fmt_money($debts['receivables']).' '.$this->currency(),
                'trend' => null,
                'tone' => 'blue',
                'icon' => 'receipt',
            ],
            [
                'label' => 'Créances en retard',
                'value' => fmt_money($debts['overdue_total']).' '.$this->currency(),
                'trend' => null,
                'tone' => 'rose',
                'icon' => 'alert',
            ],
        ];

        return view('pharma::livewire.reporting.finance', [
            'activePage' => 'finance',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'cards' => $cards,
            'payments' => $payments,
            'paymentGradient' => $this->conicGradient($payments),
            'expenses' => $expenses,
            'debts' => $debts,
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Finance',
            $service->periodLabel($from, $to)
        ));
    }
}
