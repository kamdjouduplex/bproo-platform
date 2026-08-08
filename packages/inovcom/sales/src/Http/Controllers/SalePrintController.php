<?php

namespace InovCom\Sales\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use InovCom\Kernel\Contracts\PrescriptionsApi;
use InovCom\Sales\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalePrintController
{
    /**
     * Print sale as ticket (thermal receipt) or A4 invoice.
     * GET /app/sales/{sale}/print?type=ticket|invoice
     */
    public function __invoke(Request $request, Sale $sale): View
    {
        $type = $request->query('type', 'ticket');
        if (!in_array($type, ['ticket', 'invoice'], true)) {
            $type = 'ticket';
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $sale->loadMissing(['lines', 'payments', 'client']);

        $view = $type === 'invoice'
            ? 'inovcom-sales::print.invoice'
            : 'inovcom-sales::print.ticket';

        $printContext = PrintDocument::context(
            $request,
            $type === 'invoice' ? 'facture-vente' : 'recu-vente',
            $sale->sale_number,
            $type === 'invoice' ? 'tenant.sales.index' : 'tenant.sales.create'
        );

        return view($view, array_merge([
            'sale' => $sale,
            'settings' => $settings,
            'currency' => $settings['currency'] ?? 'XOF',
            'printPageSize' => $type === 'ticket' ? '80mm auto' : 'A4',
            'rxSummary' => $this->rxSummary($sale),
        ], $printContext));
    }

    /**
     * @return array{number: string, status: string, status_label: string, lines: list<array{item_name: string, prescribed: float, this_sale: float, dispensed: float, remaining: float}>}|null
     */
    private function rxSummary(Sale $sale): ?array
    {
        if (! $sale->prescription_id || ! app()->bound(PrescriptionsApi::class)) {
            return null;
        }

        $api = app(PrescriptionsApi::class);
        if (! $api->isAvailable()) {
            return null;
        }

        $lines = $sale->lines->map(fn ($line) => [
            'item_id' => $line->item_id,
            'quantity' => $line->quantity,
            'conversion_factor' => $line->conversion_factor ?? 1,
            'item_name' => $line->item_name,
            'metadata' => $line->metadata,
        ])->all();

        return $api->saleDispensationSummary((int) $sale->prescription_id, $lines);
    }
}
