<?php

namespace InovCom\Devis\Services;

use InovCom\Devis\Models\Quote;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Maintenance\Services\CreateMaintenanceContractFromQuoteService;

class QuoteAcceptanceService
{
    /**
     * Route post-acceptance execution by offer category.
     */
    public function execute(Quote $quote): QuoteAcceptanceResult
    {
        $quote->loadMissing(['client', 'offer']);

        $category = $quote->offer?->category ?? ServiceCatalog::OFFER_PROJECT;

        if ($category === ServiceCatalog::OFFER_MAINTENANCE) {
            if (!class_exists(\InovCom\Maintenance\Models\MaintenanceContract::class)) {
                throw new \RuntimeException(__('Le module maintenance n\'est pas disponible pour ce devis.'));
            }

            $contract = app(CreateMaintenanceContractFromQuoteService::class)->create($quote);

            return new QuoteAcceptanceResult(contract: $contract);
        }

        $project = app(CreateProjectFromQuoteService::class)->create($quote);

        return new QuoteAcceptanceResult(project: $project);
    }

    public function offerCategory(Quote $quote): string
    {
        $quote->loadMissing('offer');

        return $quote->offer?->category ?? ServiceCatalog::OFFER_PROJECT;
    }

    public function isMaintenanceQuote(Quote $quote): bool
    {
        return $this->offerCategory($quote) === ServiceCatalog::OFFER_MAINTENANCE;
    }

    public function isServiceQuote(Quote $quote): bool
    {
        return $this->offerCategory($quote) === ServiceCatalog::OFFER_SERVICE;
    }
}
