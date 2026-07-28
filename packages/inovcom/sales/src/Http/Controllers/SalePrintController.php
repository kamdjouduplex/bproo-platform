<?php

namespace InovCom\Sales\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
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
        ], $printContext));
    }

}
