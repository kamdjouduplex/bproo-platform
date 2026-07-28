<?php

namespace InovCom\Returns\Services;

use Illuminate\Support\Facades\Schema;
use InovCom\Returns\Enums\ItemCondition;
use InovCom\Returns\Models\ReturnItem;
use InovCom\Returns\Models\ReturnRequest;

/**
 * Façade vers le moteur de stock existant (StockService).
 * Le stock n'est jamais modifié directement : on poste un mouvement.
 * Idempotent : un retour ne réintègre qu'une seule fois.
 */
class ReturnStockService
{
    public const REFERENCE_TYPE = 'return';

    public function isReady(): bool
    {
        return class_exists(\InovCom\Stock\Services\StockService::class)
            && Schema::connection('tenant')->hasTable('stock_levels')
            && Schema::connection('tenant')->hasTable('stock_movements');
    }

    /**
     * Réintègre le stock vendable pour les lignes contrôlées « revendables ».
     * Les lignes défectueuses / endommagées ne réintègrent pas le stock vendable.
     *
     * @return int Nombre de lignes réintégrées
     */
    public function reintegrate(ReturnRequest $return, ?int $userId = null): int
    {
        if (! $this->isReady()) {
            return 0;
        }

        if ($this->alreadyReintegrated($return->id)) {
            return 0;
        }

        $stock = app(\InovCom\Stock\Services\StockService::class);
        $count = 0;

        foreach ($return->items as $item) {
            if (! $item->item_id) {
                continue;
            }

            $restockable = $item->restock
                && ($item->condition === null || $item->condition === ItemCondition::Resellable);

            if (! $restockable) {
                continue;
            }

            $qty = (float) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $stock->addStock(
                itemId: (int) $item->item_id,
                quantity: $qty,
                type: 'in',
                referenceType: self::REFERENCE_TYPE,
                referenceId: (int) $return->id,
                reason: 'Retour client ' . $return->return_number,
                userId: $userId,
                storeId: $return->store_id ? (int) $return->store_id : null,
            );

            $item->restocked_quantity = $qty;
            $item->save();
            $count++;
        }

        return $count;
    }

    private function alreadyReintegrated(int $returnId): bool
    {
        return \InovCom\Stock\Models\StockMovement::query()
            ->where('reference_type', self::REFERENCE_TYPE)
            ->where('reference_id', $returnId)
            ->exists();
    }

    /**
     * Sortie de stock pour un produit remis lors d'un remplacement / échange.
     */
    public function issueReplacement(int $itemId, float $quantity, ReturnRequest $return, string $referenceType = 'replacement', ?int $userId = null): void
    {
        if (! $this->isReady() || $quantity <= 0) {
            return;
        }

        app(\InovCom\Stock\Services\StockService::class)->removeStock(
            itemId: $itemId,
            quantity: $quantity,
            type: 'out',
            referenceType: $referenceType,
            referenceId: (int) $return->id,
            reason: ucfirst($referenceType) . ' ' . $return->return_number,
            userId: $userId,
            storeId: $return->store_id ? (int) $return->store_id : null,
        );
    }
}
