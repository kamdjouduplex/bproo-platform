<?php

namespace InovCom\Prescriptions\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use InovCom\Prescriptions\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrescriptionPrintController
{
    public function __invoke(Request $request, Prescription $prescription): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $prescription->loadMissing(['client', 'lines.item']);

        $printContext = PrintDocument::context(
            $request,
            'ordonnance',
            $prescription->number,
            'tenant.prescriptions.index'
        );

        return view('inovcom-prescriptions::print.ordonnance', array_merge([
            'prescription' => $prescription,
            'settings' => $settings,
        ], $printContext));
    }
}
