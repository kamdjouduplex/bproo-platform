<?php

namespace InovCom\Invoicing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use InovCom\Invoicing\Services\CollectionReminderService;

class CollectionReminderPdfController
{
    public function __invoke(Request $request)
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $settings['collection_reminder_body'] = (string) $tenant?->getSetting(
            'collection_reminder_body',
            "Nous constatons que les factures ci-dessous demeurent impayées malgré l'échéance contractuelle. "
            . "Nous vous remercions de bien vouloir procéder à leur règlement dans les meilleurs délais."
        );
        $settings['shop_bank_details'] = (string) $tenant?->getSetting('shop_bank_details', '');

        $service = app(CollectionReminderService::class);
        $filters = $service->filtersFromRequest($request->all());
        $groups = $service->groupedOverdueInvoices($filters);

        if ($request->filled('client_id')) {
            $groups = $groups->filter(fn ($g) => (int) $g['client']->id === (int) $request->client_id)->values();
        }

        $city = 'Douala';
        if (preg_match('/\b(Douala|Yaoundé|Yaounde)\b/i', $settings['shop_address'] ?? '', $m)) {
            $city = $m[1];
        }

        $pdf = Pdf::loadView('inovcom-invoicing::print.collection-reminder', [
            'groups' => $groups,
            'settings' => $settings,
            'totals' => $service->globalTotals($groups),
            'letterDate' => now(),
            'letterCity' => $city,
            'letterReference' => $service->generateLetterReference(
                $request->filled('client_id') ? (int) $request->client_id : null
            ),
            'forPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('relance-factures-' . now()->format('Y-m-d') . '.pdf');
    }
}
