<?php

namespace InovCom\Items\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\ItemSetComponent;
use InovCom\Stock\Services\StockService;

class ItemSetService
{
    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('item_set_components');
    }

    public function isSet(Item|int $item): bool
    {
        $item = $item instanceof Item ? $item : Item::find($item);
        if (!$item) {
            return false;
        }

        $meta = $item->metadata ?? [];

        return !empty($meta['is_set']);
    }

    /**
     * @return Collection<int, ItemSetComponent>
     */
    public function components(int $setItemId): Collection
    {
        if (!$this->isAvailable()) {
            return collect();
        }

        return ItemSetComponent::query()
            ->with('componentItem')
            ->where('set_item_id', $setItemId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, array{item_id: int, item_name: string, item_sku: string|null, quantity_per_set: float}>
     */
    public function componentSnapshot(int $setItemId): array
    {
        return $this->components($setItemId)->map(function (ItemSetComponent $row) {
            $item = $row->componentItem;

            return [
                'item_id' => (int) $row->component_item_id,
                'item_name' => $item?->name ?? 'Article #' . $row->component_item_id,
                'item_sku' => $item?->sku,
                'quantity_per_set' => (float) $row->quantity,
            ];
        })->values()->all();
    }

    /**
     * Quantité en unité de base par composant pour N lots vendus.
     *
     * @return array<int, array{item_id: int, item_name: string, quantity_base: float, batch_tracked: bool}>
     */
    public function expandForStock(int $setItemId, float $setQuantity, float $setConversionFactor = 1.0): array
    {
        $multiplier = max(0, $setQuantity) * max(0.0001, $setConversionFactor);
        $lines = [];

        foreach ($this->components($setItemId) as $row) {
            $item = $row->componentItem;
            $meta = is_array($item?->metadata) ? $item->metadata : [];

            $lines[] = [
                'item_id' => (int) $row->component_item_id,
                'item_name' => $item?->name ?? 'Article #' . $row->component_item_id,
                'quantity_base' => round((float) $row->quantity * $multiplier, 3),
                'batch_tracked' => !empty($meta['batch_tracked']),
            ];
        }

        return $lines;
    }

    public function maxSellableQuantity(int $setItemId, ?int $storeId = null): ?float
    {
        if (!$this->isSet($setItemId) || !$this->isAvailable()) {
            return null;
        }

        $components = $this->components($setItemId);
        if ($components->isEmpty()) {
            return 0.0;
        }

        if (!Schema::connection('tenant')->hasTable('stock_levels')
            || !app()->bound(StockService::class)) {
            return null;
        }

        $stockService = app(StockService::class);
        $maxSets = null;

        foreach ($components as $row) {
            $perSet = (float) $row->quantity;
            if ($perSet <= 0) {
                continue;
            }

            $available = $stockService->getAvailableQuantity((int) $row->component_item_id, $storeId);
            $possible = floor($available / $perSet * 1000) / 1000;

            $maxSets = $maxSets === null ? $possible : min($maxSets, $possible);
        }

        return $maxSets ?? 0.0;
    }

    /**
     * @param  array<int, array{component_item_id: int, quantity: float}>  $rows
     */
    public function syncComponents(int $setItemId, array $rows): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        ItemSetComponent::query()->where('set_item_id', $setItemId)->delete();

        $sort = 0;
        foreach ($rows as $row) {
            $componentId = (int) ($row['component_item_id'] ?? 0);
            $qty = (float) ($row['quantity'] ?? 0);

            if ($componentId <= 0 || $componentId === $setItemId || $qty <= 0) {
                continue;
            }

            if ($this->isSet($componentId)) {
                throw new \InvalidArgumentException('Un lot ne peut pas contenir un autre lot comme composant.');
            }

            ItemSetComponent::create([
                'set_item_id' => $setItemId,
                'component_item_id' => $componentId,
                'quantity' => $qty,
                'sort_order' => $sort++,
            ]);
        }
    }
}
