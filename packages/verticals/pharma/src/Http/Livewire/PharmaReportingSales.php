<?php

namespace Pharma\Http\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Pharma\Concerns\InteractsWithPharmaReporting;
use Pharma\Services\PharmaReportingService;

class PharmaReportingSales extends Component
{
    use InteractsWithPharmaReporting;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'category', except: '')]
    public string $categoryId = '';

    #[Url(as: 'seller', except: '')]
    public string $userId = '';

    #[Url(as: 'store', except: '')]
    public string $storeId = '';

    #[Url(as: 'pay', except: '')]
    public string $paymentMethod = '';

    public int $perPage = 25;

    public function mount(): void
    {
        $this->bootReporting();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function updatedStoreId(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentMethod(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->categoryId = '';
        $this->userId = '';
        $this->storeId = '';
        $this->paymentMethod = '';
        $this->applyPeriodPreset('this_month');
        $this->resetPage();
    }

    public function exportUrl(string $format): string
    {
        $route = $format === 'pdf'
            ? 'tenant.pharma-reporting.sales.print'
            : 'tenant.pharma-reporting.sales.excel';

        return route($route, array_filter([
            ...$this->periodQuery(),
            'q' => $this->search ?: null,
            'category' => $this->categoryId ?: null,
            'seller' => $this->userId ?: null,
            'store' => $this->storeId ?: null,
            'pay' => $this->paymentMethod ?: null,
        ]));
    }

    public function render()
    {
        $service = app(PharmaReportingService::class);
        [$from, $to] = $this->currentRange();
        [$prevFrom, $prevTo] = $service->previousRange($from, $to);
        $filters = [
            'category_id' => $this->categoryId !== '' ? (int) $this->categoryId : null,
            'user_id' => $this->userId !== '' ? (int) $this->userId : null,
            'store_id' => $this->storeId !== '' ? (int) $this->storeId : null,
            'payment_method' => $this->paymentMethod !== '' ? $this->paymentMethod : null,
            'search' => $this->search,
        ];
        $kpis = $service->salesListKpis($from, $to, $filters);
        $prev = $service->salesListKpis($prevFrom, $prevTo, $filters);

        $query = $service->salesListQuery($from, $to, $filters);
        $rows = $query->paginate($this->perPage);
        $options = $service->filterOptions();

        $cards = [
            [
                'label' => 'Chiffre d’affaires',
                'value' => fmt_money($kpis['ca']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['ca'], $prev['ca']),
                'tone' => 'teal',
                'icon' => 'shopping-bag',
            ],
            [
                'label' => 'Nombre de ventes',
                'value' => fmt_num($kpis['sales_count'], 0),
                'trend' => $service->trendPct((float) $kpis['sales_count'], (float) $prev['sales_count']),
                'tone' => 'blue',
                'icon' => 'receipt',
            ],
            [
                'label' => 'Produits vendus',
                'value' => fmt_num($kpis['qty_sold'], 0),
                'trend' => $service->trendPct($kpis['qty_sold'], $prev['qty_sold']),
                'tone' => 'violet',
                'icon' => 'package',
            ],
            [
                'label' => 'Panier moyen',
                'value' => fmt_money($kpis['avg_basket']).' '.$this->currency(),
                'trend' => $service->trendPct($kpis['avg_basket'], $prev['avg_basket']),
                'tone' => 'amber',
                'icon' => 'document',
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
        ];

        return view('pharma::livewire.reporting.sales', [
            'activePage' => 'sales',
            'tenantCode' => $this->tenantCode(),
            'currency' => $this->currency(),
            'periodLabel' => $service->periodLabel($from, $to),
            'canExport' => $this->canExport(),
            'cards' => $cards,
            'rows' => $rows,
            'options' => $options,
            'service' => $service,
            'periodQuery' => $this->periodQuery(),
        ])->layout('layouts.app', $this->reportingLayout(
            'Analyse des ventes',
            $service->periodLabel($from, $to)
        ));
    }
}
