<?php

namespace InovCom\Invoicing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use App\Support\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InovCom\Invoicing\Services\CollectionReminderService;

class CollectionReminderPrintController
{
    public function __invoke(Request $request): View
    {
        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);
        $settings['collection_reminder_body'] = (string) $tenant?->getSetting(
            'collection_reminder_body',
            $this->defaultBody()
        );
        $settings['shop_bank_details'] = (string) $tenant?->getSetting('shop_bank_details', '');

        $service = app(CollectionReminderService::class);
        $filters = $service->filtersFromRequest($request->all());
        $groups = $service->groupedOverdueInvoices($filters);

        if ($request->filled('client_id')) {
            $groups = $groups->filter(fn ($g) => (int) $g['client']->id === (int) $request->client_id)->values();
        }

        $letterDate = now();
        $city = $this->extractCity($settings['shop_address'] ?? '');
        $letterReference = $service->generateLetterReference(
            $request->filled('client_id') ? (int) $request->client_id : null
        );

        return view('inovcom-invoicing::print.collection-reminder', array_merge([
            'groups' => $groups,
            'settings' => $settings,
            'totals' => $service->globalTotals($groups),
            'letterDate' => $letterDate,
            'letterCity' => $city,
            'letterReference' => $letterReference,
        ], PrintDocument::context(
            $request,
            'fiche-relance',
            $letterReference,
            'tenant.invoicing.collection_reminders.index'
        )));
    }

    private function defaultBody(): string
    {
        return "Nous constatons que les factures ci-dessous demeurent impayées malgré l'échéance contractuelle. "
            . "Nous vous remercions de bien vouloir procéder à leur règlement dans les meilleurs délais. "
            . "Pour toute information complémentaire, merci de contacter notre service comptable.";
    }

    private function extractCity(string $address): string
    {
        if (preg_match('/\b(Douala|Yaoundé|Yaounde|Abidjan|Dakar|Libreville)\b/i', $address, $m)) {
            return $m[1];
        }

        return 'Douala';
    }
}
