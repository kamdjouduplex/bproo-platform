<?php

namespace InovCom\Invoicing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Support\AmountInWords;

class InvoicePrintController
{
    public function __invoke(Request $request, Invoice $invoice): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $invoice->loadMissing(['lines', 'client', 'quotation.lines', 'taxLines']);

        $currencyLabel = ($settings['currency'] ?? 'XOF') === 'XOF' ? 'Franc(s) CFA' : $settings['currency'];

        $montantHt = max(0, round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2));
        $taxSummary = \App\Support\DocumentTaxCalculator::summarizeFromStoredTaxLines(
            $montantHt,
            $invoice->taxLines,
            (float) $invoice->tax_amount
        );
        $netAPayer = (float) $invoice->total > 0 ? (float) $invoice->total : $taxSummary['total'];

        return view('inovcom-invoicing::print.invoice', array_merge([
            'invoice' => $invoice,
            'settings' => $settings,
            'amountInWords' => AmountInWords::french((int) round($netAPayer), $currencyLabel),
        ], PrintDocument::context(
            $request,
            'facture',
            $invoice->invoice_number,
            'tenant.invoicing.index'
        )));
    }
}
