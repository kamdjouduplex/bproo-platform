<?php

namespace InovCom\Stock\Services;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use InovCom\Stock\Models\StockLevel;
use InovCom\Stock\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Stock service for managing inventory
 * Used by Sales, Purchases, and other modules
 */
class StockService
{
    private function resolveStoreId(?int $storeId = null): ?int
    {
        if ($storeId) {
            return $storeId;
        }
        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();
        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }

    private function stockMovementsHaveStoreColumn(): bool
    {
        return Schema::connection('tenant')->hasColumn('stock_movements', 'store_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function movementAttributes(array $attributes, ?int $storeId): array
    {
        if ($this->stockMovementsHaveStoreColumn()) {
            $attributes['store_id'] = $storeId;
        }

        return $attributes;
    }

    /**
     * Get stock level for an item (create if doesn't exist)
     */
    public function getStockLevel(int $itemId, ?int $storeId = null): StockLevel
    {
        $storeId = $this->resolveStoreId($storeId);
        $attrs = ['item_id' => $itemId];
        if (Schema::connection('tenant')->hasColumn('stock_levels', 'store_id')) {
            $attrs['store_id'] = $storeId;
        }

        return StockLevel::firstOrCreate(
            $attrs,
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
            ]
        );
    }

    /**
     * Get available quantity for an item
     */
    public function getAvailableQuantity(int $itemId, ?int $storeId = null): float
    {
        $stock = $this->getStockLevel($itemId, $storeId);
        return (float) $stock->available_quantity;
    }

    /**
     * Check if item has enough stock
     */
    public function hasStock(int $itemId, float $quantity, ?int $storeId = null): bool
    {
        return $this->getAvailableQuantity($itemId, $storeId) >= $quantity;
    }

    /**
     * Add stock (incoming)
     */
    public function addStock(
        int $itemId,
        float $quantity,
        string $type = 'in',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?int $storeId = null
    ): StockMovement {
        $storeId = $this->resolveStoreId($storeId);
        $stock = $this->getStockLevel($itemId, $storeId);
        $quantityBefore = (float) $stock->quantity;
        
        $stock->quantity += $quantity;
        $stock->updateAvailableQuantity();
        $stock->save();

        return StockMovement::create($this->movementAttributes([
            'item_id' => $itemId,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => (float) $stock->quantity,
            'reason' => $reason,
            'created_by' => $userId ?? auth('tenant')->id(),
        ], $storeId));
    }

    /**
     * Remove stock (outgoing)
     */
    public function removeStock(
        int $itemId,
        float $quantity,
        string $type = 'out',
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?int $storeId = null
    ): StockMovement {
        $storeId = $this->resolveStoreId($storeId);
        $stock = $this->getStockLevel($itemId, $storeId);
        $quantityBefore = (float) $stock->quantity;
        
        $stock->quantity = max(0, $stock->quantity - $quantity);
        $stock->updateAvailableQuantity();
        $stock->save();

        return StockMovement::create($this->movementAttributes([
            'item_id' => $itemId,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity' => -$quantity, // Negative for outgoing
            'quantity_before' => $quantityBefore,
            'quantity_after' => (float) $stock->quantity,
            'reason' => $reason,
            'created_by' => $userId ?? auth('tenant')->id(),
        ], $storeId));
    }

    /**
     * Adjust stock (manual adjustment)
     */
    public function adjustStock(
        int $itemId,
        float $newQuantity,
        ?string $reason = null,
        ?int $userId = null,
        ?int $storeId = null
    ): StockMovement {
        $storeId = $this->resolveStoreId($storeId);
        $stock = $this->getStockLevel($itemId, $storeId);
        $quantityBefore = (float) $stock->quantity;
        $difference = $newQuantity - $quantityBefore;
        
        $stock->quantity = $newQuantity;
        $stock->updateAvailableQuantity();
        $stock->save();

        return StockMovement::create($this->movementAttributes([
            'item_id' => $itemId,
            'type' => 'adjustment',
            'quantity' => $difference,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $newQuantity,
            'reason' => $reason ?? 'Ajustement manuel',
            'created_by' => $userId ?? auth('tenant')->id(),
        ], $storeId));
    }

    /**
     * Get low stock items
     */
    public function getLowStockItems(): Collection
    {
        $storeId = $this->resolveStoreId();
        return StockLevel::query()
            ->when(
                Schema::connection('tenant')->hasColumn('stock_levels', 'store_id') && $storeId,
                fn ($q) => $q->where('store_id', $storeId)
            )
            ->whereNotNull('reorder_point')
            ->whereRaw('available_quantity <= reorder_point')
            ->with('item')
            ->get();
    }

    /**
     * Get stock movements for an item
     */
    public function getItemMovements(int $itemId, int $limit = 50): Collection
    {
        $storeId = $this->resolveStoreId();
        return StockMovement::where('item_id', $itemId)
            ->when($this->stockMovementsHaveStoreColumn() && $storeId, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function transferStock(
        int $itemId,
        float $quantity,
        int $fromStoreId,
        int $toStoreId,
        ?string $reason = null,
        ?int $userId = null
    ): void {
        if ($fromStoreId === $toStoreId) {
            throw new \InvalidArgumentException('Le magasin source et destination doivent être différents.');
        }

        $this->removeStock($itemId, $quantity, 'transfer', 'store_transfer', null, $reason ?: 'Transfert sortant', $userId, $fromStoreId);
        $this->addStock($itemId, $quantity, 'transfer', 'store_transfer', null, $reason ?: 'Transfert entrant', $userId, $toStoreId);
    }

    /**
     * Réserve du stock (diminue le disponible sans sortie physique).
     */
    public function reserveStock(
        int $itemId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?int $storeId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité réservée doit être positive.');
        }

        if (! $this->hasStock($itemId, $quantity, $storeId)) {
            throw new \RuntimeException('Stock disponible insuffisant pour la réservation.');
        }

        $storeId = $this->resolveStoreId($storeId);
        $stock = $this->getStockLevel($itemId, $storeId);
        $physical = (float) $stock->quantity;
        $reservedBefore = (float) $stock->reserved_quantity;
        $availableBefore = max(0.0, $physical - $reservedBefore);

        $stock->reserved_quantity = $reservedBefore + $quantity;
        $stock->updateAvailableQuantity();
        $stock->save();

        return StockMovement::create($this->movementAttributes([
            'item_id' => $itemId,
            'type' => 'reserve',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            // Quantité réservée (pas une sortie physique) — avant/après = stock disponible
            'quantity' => $quantity,
            'quantity_before' => $availableBefore,
            'quantity_after' => (float) $stock->available_quantity,
            'reason' => $reason ?? ('Réservation +' . fmt_num($quantity, 3)),
            'created_by' => $userId ?? auth('tenant')->id(),
        ], $storeId));
    }

    /**
     * Libère une quantité précédemment réservée.
     */
    public function releaseStock(
        int $itemId,
        float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?int $storeId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité à libérer doit être positive.');
        }

        $storeId = $this->resolveStoreId($storeId);
        $stock = $this->getStockLevel($itemId, $storeId);
        $physical = (float) $stock->quantity;
        $reserved = (float) $stock->reserved_quantity;
        $availableBefore = max(0.0, $physical - $reserved);

        if ($reserved + 1e-9 < $quantity) {
            throw new \RuntimeException('Quantité réservée insuffisante à libérer.');
        }

        $stock->reserved_quantity = max(0, $reserved - $quantity);
        $stock->updateAvailableQuantity();
        $stock->save();

        return StockMovement::create($this->movementAttributes([
            'item_id' => $itemId,
            'type' => 'release',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            // Négatif = quantité libérée (redevient disponible)
            'quantity' => -$quantity,
            'quantity_before' => $availableBefore,
            'quantity_after' => (float) $stock->available_quantity,
            'reason' => $reason ?? ('Libération réservation -' . fmt_num($quantity, 3)),
            'created_by' => $userId ?? auth('tenant')->id(),
        ], $storeId));
    }

    /**
     * Transfère une réservation vers un autre document (ex. devis) sans changer le disponible net.
     */
    public function transferReservation(
        int $itemId,
        float $quantity,
        string $fromReferenceType,
        int $fromReferenceId,
        string $toReferenceType,
        int $toReferenceId,
        ?string $reason = null,
        ?int $userId = null,
        ?int $storeId = null
    ): void {
        $this->releaseStock($itemId, $quantity, $fromReferenceType, $fromReferenceId, $reason, $userId, $storeId);

        $storeId = $this->resolveStoreId($storeId);
        $stock = $this->getStockLevel($itemId, $storeId);
        $physical = (float) $stock->quantity;
        $reservedBefore = (float) $stock->reserved_quantity;
        $availableBefore = max(0.0, $physical - $reservedBefore);

        $stock->reserved_quantity = $reservedBefore + $quantity;
        $stock->updateAvailableQuantity();
        $stock->save();

        StockMovement::create($this->movementAttributes([
            'item_id' => $itemId,
            'type' => 'reserve',
            'reference_type' => $toReferenceType,
            'reference_id' => $toReferenceId,
            'quantity' => $quantity,
            'quantity_before' => $availableBefore,
            'quantity_after' => (float) $stock->available_quantity,
            'reason' => $reason ?? ('Transfert réservation +' . fmt_num($quantity, 3)),
            'created_by' => $userId ?? auth('tenant')->id(),
        ], $storeId));
    }
}
