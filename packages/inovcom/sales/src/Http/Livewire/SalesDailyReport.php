<?php

namespace InovCom\Sales\Http\Livewire;

use App\Services\TenantCurrencyService;
use App\Services\TenantManager;
use InovCom\Sales\Models\Sale;
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
     * @return array<string, mixed>
     */
    protected function reportData(): array
    {
        $tenant = app(TenantManager::class)->tenant();
        $defaultCurrency = $tenant
            ? app(TenantCurrencyService::class)->defaultCode($tenant)
            : 'XOF';

        $sales = Sale::query()
            ->whereDate('sale_date', $this->date)
            ->with(['lines', 'client', 'creator'])
            ->orderBy('id')
            ->get();

        $totalsByCurrency = [];
        foreach ($sales as $sale) {
            $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency));
            $totalsByCurrency[$code] = ($totalsByCurrency[$code] ?? 0) + (float) $sale->total;
        }

        $detailLines = collect();
        foreach ($sales as $sale) {
            $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency));
            foreach ($sale->lines as $line) {
                $detailLines->push([
                    'sale_number' => $sale->sale_number,
                    'item_name' => $line->item_name,
                    'item_sku' => $line->item_sku,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                    'currency_code' => $code,
                    'currency_label' => TenantCurrencyService::label($code),
                ]);
            }
        }

        return [
            'sales' => $sales,
            'detailLines' => $detailLines,
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
                'subtitle' => 'Liste des ventes et détail articles',
            ]);
    }
}
