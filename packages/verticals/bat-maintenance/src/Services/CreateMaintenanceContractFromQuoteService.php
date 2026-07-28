<?php

namespace InovCom\Maintenance\Services;

use InovCom\Devis\Models\Quote;
use InovCom\Kernel\Support\ServiceCatalog;
use InovCom\Maintenance\Models\MaintenanceContract;
use Illuminate\Support\Facades\Log;

class CreateMaintenanceContractFromQuoteService
{
    /**
     * Create a maintenance contract from an accepted quote (idempotent).
     */
    public function create(Quote $quote): MaintenanceContract
    {
        if (!class_exists(MaintenanceContract::class)) {
            throw new \RuntimeException(__('Le module maintenance n\'est pas disponible.'));
        }

        if ($quote->status !== 'accepted') {
            throw new \RuntimeException(__('Seuls les devis acceptés peuvent générer un contrat.'));
        }

        $existing = MaintenanceContract::on('tenant')->where('quote_id', $quote->id)->first();
        if ($existing) {
            return $existing;
        }

        $quote->loadMissing(['client', 'offer']);

        if (($quote->offer?->category ?? null) !== ServiceCatalog::OFFER_MAINTENANCE) {
            throw new \RuntimeException(
                __('Seuls les devis liés à une offre maintenance peuvent générer un contrat.')
            );
        }

        $startDate = now()->toDateString();
        $endDate   = $quote->valid_until?->format('Y-m-d')
            ?? now()->addYear()->toDateString();

        $monthlyPrice = null;
        if ((float) $quote->total_ttc > 0) {
            $monthlyPrice = round((float) $quote->total_ttc / 12, 2);
        }

        $termsParts = array_filter([
            $quote->terms,
            $quote->notes,
            __('Contrat généré automatiquement depuis le devis :code.', ['code' => $quote->code]),
        ]);

        $contract = MaintenanceContract::create([
            'code'            => $this->generateNextCode(),
            'client_id'       => $quote->client_id,
            'quote_id'        => $quote->id,
            'offer_id'        => $quote->offer_id,
            'title'           => $quote->title,
            'type'            => 'full_service',
            'status'          => 'active',
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'price_per_month' => $monthlyPrice,
            'response_time'   => 24,
            'resolution_time' => 72,
            'billing_cycle'   => 'monthly',
            'intervention_frequency' => 'monthly',
            'next_intervention_at'   => $startDate,
            'auto_generate_orders'   => true,
            'terms'           => implode("\n\n", $termsParts) ?: null,
        ]);

        Log::info('CreateMaintenanceContractFromQuote: contract created.', [
            'contract_code' => $contract->code,
            'quote_code'    => $quote->code,
        ]);

        return $contract;
    }

    private function generateNextCode(): string
    {
        $max = MaintenanceContract::on('tenant')
            ->where('code', 'like', 'CTR%')
            ->pluck('code')
            ->map(fn (string $c): int => (int) substr($c, 3))
            ->filter(fn (int $n): bool => $n > 0)
            ->max();

        return 'CTR' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }
}
