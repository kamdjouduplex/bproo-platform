<?php

namespace InovCom\Sales\Http\Livewire;

use App\Services\TenantCurrencyService;
use App\Services\TenantManager;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleLine;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class SalesDailyReport extends Component
{
    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function setToday(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    /**
     * @return array{lines: \Illuminate\Support\Collection, totalsByCurrency: array<string,float>, salesCount: int, defaultCurrency: string}
     */
    protected function reportData(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $defaultCurrency = $tenant
            ? app(TenantCurrencyService::class)->defaultCode($tenant)
            : 'XOF';

        $sales = Sale::query()
            ->whereDate('sale_date', $this->date)
            ->get(['id', 'currency_code', 'total']);

        $totalsByCurrency = [];
        foreach ($sales as $sale) {
            $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency));
            $totalsByCurrency[$code] = ($totalsByCurrency[$code] ?? 0) + (float) $sale->total;
        }

        $saleIds = $sales->pluck('id')->all();
        $lines = collect();
        if ($saleIds !== []) {
            $lines = SaleLine::query()
                ->whereIn('sale_id', $saleIds)
                ->with('sale:id,sale_number,currency_code,sale_date')
                ->orderBy('item_name')
                ->get()
                ->groupBy(function (SaleLine $line) use ($defaultCurrency) {
                    $code = strtoupper((string) ($line->sale?->currency_code ?: $defaultCurrency));

                    return $line->item_id.'|'.$line->item_name.'|'.$code;
                })
                ->map(function ($group) use ($defaultCurrency) {
                    $first = $group->first();
                    $code = strtoupper((string) ($first->sale?->currency_code ?: $defaultCurrency));

                    return [
                        'item_name' => $first->item_name,
                        'item_sku' => $first->item_sku,
                        'currency_code' => $code,
                        'currency_label' => TenantCurrencyService::label($code),
                        'quantity' => round($group->sum(fn ($l) => (float) $l->quantity), 3),
                        'amount' => round($group->sum(fn ($l) => (float) $l->line_total), 2),
                    ];
                })
                ->sortBy(['item_name', 'currency_code'])
                ->values();
        }

        return [
            'lines' => $lines,
            'totalsByCurrency' => $totalsByCurrency,
            'salesCount' => $sales->count(),
            'defaultCurrency' => $defaultCurrency,
            'hasCurrencyColumn' => Schema::connection('tenant')->hasColumn('sales', 'currency_code'),
        ];
    }

    public function render()
    {
        $data = $this->reportData();

        return view('inovcom-sales::livewire.sales.daily-report', $data)
            ->layout('layouts.app', [
                'title' => 'Rapport ventes du jour',
                'subtitle' => 'Médicaments / articles vendus',
            ]);
    }
}
