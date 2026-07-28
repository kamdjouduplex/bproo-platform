<?php

namespace InovCom\Invoicing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Support\DeliveryNotePrintData;
use InovCom\Invoicing\Support\DeliveryNotePrintSettings;

class DeliveryNotePrintController
{
    public function __invoke(Request $request, DeliveryNote $deliveryNote): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $branding = app(TenantBrandingService::class);

        $deliveryNote->refresh();
        $deliveryNote->loadMissing([
            'lines',
            'lines.quotationLine',
            'lines.invoiceLine',
            'invoice.client',
            'invoice.lines',
            'quotation.client',
            'client',
            'creator',
            'confirmer',
        ]);

        $client = $deliveryNote->invoice?->client
            ?? $deliveryNote->client
            ?? $deliveryNote->quotation?->client;

        $purchaseOrder = DeliveryNotePrintSettings::resolvePurchaseOrder($deliveryNote, $request);
        $showPrices = DeliveryNotePrintSettings::resolveShowPrices($deliveryNote, $request);
        $showDiscounts = DeliveryNotePrintSettings::resolveShowDiscounts($deliveryNote, $request);

        if ($showDiscounts && !$showPrices) {
            $showDiscounts = false;
        }

        $printData = DeliveryNotePrintData::build($deliveryNote, $purchaseOrder);

        return view('inovcom-invoicing::print.delivery-note', array_merge([
            'deliveryNote' => $deliveryNote,
            'invoice' => $deliveryNote->invoice,
            'client' => $client,
            'settings' => $settings,
            'watermark' => $branding->deliveryNoteWatermark($deliveryNote->status),
            'printData' => $printData,
            'showPrices' => $showPrices,
            'showDiscounts' => $showDiscounts,
        ], PrintDocument::context(
            $request,
            'bon-livraison',
            $deliveryNote->delivery_number,
            'tenant.invoicing.deliveries.index'
        )));
    }
}
