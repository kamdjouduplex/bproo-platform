<?php

namespace InovCom\InvoicePayments\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\InvoicePayments\Support\PaymentSettlementLines;

class InvoicePaymentPrintController
{
    public function __invoke(Request $request, InvoicePayment $invoicePayment): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $invoicePayment->loadMissing(array_merge(
            ['invoice.client', 'invoice.taxLines', 'creator', 'canceller'],
            InvoicePayment::optionalWithholdingsRelation(),
            InvoicePayment::optionalAttachmentsRelation()
        ));

        $invoice = $invoicePayment->invoice;
        $settlement = $invoice
            ? PaymentSettlementLines::fromModels($invoice, $invoicePayment)
            : null;

        return view('inovcom-invoice-payments::print.payment-receipt', array_merge([
            'payment' => $invoicePayment,
            'invoice' => $invoice,
            'settings' => $settings,
            'settlement' => $settlement,
        ], PrintDocument::context(
            $request,
            'recu-paiement',
            $invoicePayment->invoice?->invoice_number . '-' . $invoicePayment->id,
            'tenant.invoice_payments.index'
        )));
    }
}
