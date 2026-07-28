<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Returns\Models\CustomerCredit;

/**
 * Portefeuille (wallet) de crédit client : ledger d'écritures, solde calculé.
 */
class CustomerCreditService
{
    public function isReady(): bool
    {
        return Schema::connection('tenant')->hasTable('customer_credits');
    }

    public function balance(int $clientId): float
    {
        if (! $this->isReady()) {
            return 0.0;
        }

        $credit = (float) CustomerCredit::query()
            ->where('client_id', $clientId)
            ->where('direction', 'credit')
            ->sum('amount');

        $debit = (float) CustomerCredit::query()
            ->where('client_id', $clientId)
            ->where('direction', 'debit')
            ->sum('amount');

        return round($credit - $debit, 2);
    }

    /**
     * Alimente le portefeuille (avoir converti en crédit, trop-perçu, etc.).
     */
    public function grant(
        int $clientId,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $userId = null
    ): CustomerCredit {
        return $this->record($clientId, 'credit', $amount, $sourceType, $sourceId, $reference, $notes, $userId);
    }

    /**
     * Consomme du crédit client (imputation sur une vente/facture future).
     */
    public function consume(
        int $clientId,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $userId = null
    ): CustomerCredit {
        if ($amount > $this->balance($clientId) + 0.01) {
            throw new \RuntimeException('Crédit client insuffisant pour cette opération.');
        }

        return $this->record($clientId, 'debit', $amount, $sourceType, $sourceId, $reference, $notes, $userId);
    }

    private function record(
        int $clientId,
        string $direction,
        float $amount,
        string $sourceType,
        ?int $sourceId,
        ?string $reference,
        ?string $notes,
        ?int $userId
    ): CustomerCredit {
        if (! $this->isReady()) {
            throw new \RuntimeException('Le portefeuille de crédit client n\'est pas initialisé.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être strictement positif.');
        }

        return DB::connection('tenant')->transaction(function () use (
            $clientId, $direction, $amount, $sourceType, $sourceId, $reference, $notes, $userId
        ) {
            // Verrou logique : on relit le solde sous transaction.
            $current = $this->balance($clientId);
            $balanceAfter = $direction === 'credit'
                ? $current + $amount
                : $current - $amount;

            return CustomerCredit::create([
                'client_id' => $clientId,
                'direction' => $direction,
                'amount' => $amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'balance_after' => round($balanceAfter, 2),
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => $userId ?? auth('tenant')->id(),
            ]);
        });
    }
}
