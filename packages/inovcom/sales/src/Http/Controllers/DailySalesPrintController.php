<?php

namespace InovCom\Sales\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantCurrencyService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use InovCom\Sales\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DailySalesPrintController
{
    public function __invoke(Request $request): View
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $defaultCurrency = $tenant
            ? app(TenantCurrencyService::class)->defaultCode($tenant)
            : (string) ($settings['currency'] ?? 'XOF');

        $sales = Sale::query()
            ->whereDate('sale_date', $date)
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

        $printContext = PrintDocument::context(
            $request,
            'rapport-ventes-jour',
            'ventes-'.$date,
            'tenant.sales.daily-report'
        );

        return view('inovcom-sales::print.daily-report', array_merge([
            'date' => $date,
            'sales' => $sales,
            'detailLines' => $detailLines,
            'totalsByCurrency' => $totalsByCurrency,
            'settings' => $settings,
            'defaultCurrency' => $defaultCurrency,
            'printPageSize' => 'A4',
        ], $printContext));
    }
}
