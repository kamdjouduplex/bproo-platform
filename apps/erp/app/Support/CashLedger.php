<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Pont d'auto-capture entre les modules financiers et la caisse.
 *
 * Toutes les méthodes sont :
 *  - défensives : silencieuses si le module Caisse est absent ou non migré ;
 *  - idempotentes : un même mouvement métier n'est posté qu'une seule fois ;
 *  - non bloquantes : elles n'interrompent jamais l'opération source (vente, paiement…).
 *
 * Seuls les mouvements ESPÈCES transitent par la caisse. Les autres moyens
 * (Mobile Money, virement, chèque, carte, crédit) ne sont pas du ressort du tiroir physique.
 */
class CashLedger
{
    public const SALE_CASH_IN = 'sale_cash_in';
    public const SALE_RETURN_CASH_OUT = 'sale_return_cash_out';
    public const INVOICE_PAYMENT_CASH_IN = 'invoice_payment_cash_in';
    public const DEBT_PAYMENT_CASH_IN = 'debt_payment_cash_in';
    public const EXPENSE_CASH_OUT = 'expense_cash_out';

    private static function service(): ?object
    {
        if (! class_exists(\InovCom\Caisse\Services\CaisseService::class)) {
            return null;
        }

        try {
            $service = app(\InovCom\Caisse\Services\CaisseService::class);

            return $service->isReady() ? $service : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function recordIn(
        string $type,
        float $amount,
        string $reason,
        string $source,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber = null,
        array $metadata = [],
        ?int $userId = null
    ): bool {
        return self::record('in', $type, $amount, $reason, $source, $referenceType, $referenceId, $referenceNumber, $metadata, $userId);
    }

    public static function recordOut(
        string $type,
        float $amount,
        string $reason,
        string $source,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber = null,
        array $metadata = [],
        ?int $userId = null
    ): bool {
        return self::record('out', $type, $amount, $reason, $source, $referenceType, $referenceId, $referenceNumber, $metadata, $userId);
    }

    public static function record(
        string $direction,
        string $type,
        float $amount,
        string $reason,
        string $source,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber = null,
        array $metadata = [],
        ?int $userId = null
    ): bool {
        $service = self::service();
        if (! $service) {
            return false;
        }

        try {
            if (method_exists($service, 'ensureLedgerInitialized')) {
                $service->ensureLedgerInitialized();
            }

            $entry = $service->recordSystemMovement(
                $direction,
                $type,
                $amount,
                $reason,
                $source,
                $referenceType,
                $referenceId,
                $referenceNumber,
                $metadata,
                $userId
            );

            return $entry !== null;
        } catch (\Throwable $e) {
            Log::warning('CashLedger: auto-capture échouée — ' . $e->getMessage());

            return false;
        }
    }

    public static function reverse(
        string $type,
        string $referenceType,
        int $referenceId,
        string $reason,
        ?int $userId = null
    ): void {
        $service = self::service();
        if (! $service) {
            return;
        }

        try {
            $service->reverseSystemMovement($type, $referenceType, $referenceId, $reason, $userId);
        } catch (\Throwable $e) {
            Log::warning('CashLedger: contre-passation échouée — ' . $e->getMessage());
        }
    }
}
