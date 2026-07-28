<?php

namespace InovCom\Quotations\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Quotations\Models\Quotation;

class QuotationPrintController
{
    public function __invoke(Request $request, Quotation $quotation): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $quotation->loadMissing(['lines', 'client', 'taxLines']);

        $branding = app(TenantBrandingService::class);

        return view('inovcom-quotations::print.quotation', array_merge([
            'quotation' => $quotation,
            'settings' => $settings,
            'watermark' => $branding->quotationWatermark($quotation->status),
        ], PrintDocument::context(
            $request,
            'devis',
            $quotation->number,
            'tenant.quotations.index'
        )));
    }
}
