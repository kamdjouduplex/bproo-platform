<?php

namespace InovCom\Sales\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantCurrencyService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleLine;
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
            ->with(['lines', 'payments', 'client'])
            ->orderBy('id')
            ->get();

        $totalsByCurrency = [];
        foreach ($sales as $sale) {
            $code = strtoupper((string) ($sale->currency_code ?: $defaultCurrency));
            $totalsByCurrency[$code] = ($totalsByCurrency[$code] ?? 0) + (float) $sale->total;
        }

        $saleIds = $sales->pluck('id')->all();
        $aggregated = collect();
        if ($saleIds !== []) {
            $aggregated = SaleLine::query()
                ->whereIn('sale_id', $saleIds)
                ->with('sale:id,currency_code')
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

        $printContext = PrintDocument::context(
            $request,
            'rapport-ventes-jour',
            'ventes-'.$date,
            'tenant.sales.daily-report'
        );

        return view('inovcom-sales::print.daily-report', array_merge([
            'date' => $date,
            'sales' => $sales,
            'lines' => $aggregated,
            'totalsByCurrency' => $totalsByCurrency,
            'settings' => $settings,
            'defaultCurrency' => $defaultCurrency,
            'printPageSize' => 'A4',
        ], $printContext));
    }
}
