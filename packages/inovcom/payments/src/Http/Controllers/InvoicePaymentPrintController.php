<?php

namespace InovCom\InvoicePayments\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\InvoicePayments\Models\InvoicePayment;

class InvoicePaymentPrintController
{
    public function __invoke(Request $request, InvoicePayment $invoicePayment): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $invoicePayment->loadMissing(array_merge(
            ['invoice.client', 'creator'],
            InvoicePayment::optionalWithholdingsRelation()
        ));

        return view('inovcom-invoice-payments::print.payment-receipt', array_merge([
            'payment' => $invoicePayment,
            'invoice' => $invoicePayment->invoice,
            'settings' => $settings,
        ], PrintDocument::context(
            $request,
            'recu-paiement',
            $invoicePayment->invoice->invoice_number . '-' . $invoicePayment->id,
            'tenant.invoice_payments.index'
        )));
    }
}
