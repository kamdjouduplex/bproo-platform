<?php

namespace InovCom\Purchases\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Purchases\Models\ItemForeignPurchasePrice;
use InovCom\Purchases\Models\ItemPurchasePrice;
use InovCom\Purchases\Support\PurchasePriceHistoryEntry;

class PurchasePriceHistoryService
{
    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('item_purchase_prices');
    }

    public function isForeignAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('item_foreign_purchase_prices');
    }

    public function recordForeign(
        int $itemId,
        string $currencyCode,
        float $unitPriceForeign,
        ?float $unitPriceLocal = null,
        ?int $foreignPurchaseOrderId = null,
        ?int $foreignPurchaseLineId = null,
        ?int $providerId = null,
        float $quantity = 1.0
    ): ?ItemForeignPurchasePrice {
        if (!$this->isForeignAvailable() || $unitPriceForeign < 0) {
            return null;
        }

        return ItemForeignPurchasePrice::create([
            'item_id' => $itemId,
            'provider_id' => $providerId,
            'foreign_purchase_order_id' => $foreignPurchaseOrderId,
            'foreign_purchase_line_id' => $foreignPurchaseLineId,
            'currency_code' => strtoupper($currencyCode),
            'unit_price_foreign' => $unitPriceForeign,
            'unit_price_local' => $unitPriceLocal,
            'quantity' => $quantity,
            'recorded_at' => now(),
        ]);
    }

    public function latestForeignForItem(int $itemId, string $currencyCode, ?int $providerId = null): ?ItemForeignPurchasePrice
    {
        if (!$this->isForeignAvailable()) {
            return null;
        }

        $currencyCode = strtoupper($currencyCode);

        $query = ItemForeignPurchasePrice::query()
            ->where('item_id', $itemId)
            ->where('currency_code', $currencyCode)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if ($providerId) {
            $providerLatest = (clone $query)->where('provider_id', $providerId)->first();
            if ($providerLatest) {
                return $providerLatest;
            }
        }

        return $query->first();
    }

    public function latestForeignUnitPrice(int $itemId, string $currencyCode, ?int $providerId = null): ?float
    {
        $latest = $this->latestForeignForItem($itemId, $currencyCode, $providerId);

        return $latest ? (float) $latest->unit_price_foreign : null;
    }

    /** Dernier coût d'achat en devise (historique achats étrangers confirmés / réceptionnés). */
    public function latestForeignPurchaseCost(int $itemId, string $currencyCode, ?int $providerId = null): ?float
    {
        $cost = $this->latestForeignUnitPrice($itemId, $currencyCode, $providerId);

        return $cost !== null ? round($cost, 4) : null;
    }

    public function record(
        int $itemId,
        float $unitPrice,
        ?int $purchaseOrderId = null,
        ?int $purchaseLineId = null,
        ?int $providerId = null,
        float $quantity = 1.0
    ): ?ItemPurchasePrice {
        if (!$this->isAvailable() || $unitPrice < 0) {
            return null;
        }

        $entry = ItemPurchasePrice::create([
            'item_id' => $itemId,
            'provider_id' => $providerId,
            'purchase_order_id' => $purchaseOrderId,
            'purchase_line_id' => $purchaseLineId,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'recorded_at' => now(),
        ]);

        Item::whereKey($itemId)->update(['cost' => $unitPrice]);

        return $entry;
    }

    public function latestForItem(int $itemId, ?int $providerId = null): ?ItemPurchasePrice
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $query = ItemPurchasePrice::query()
            ->where('item_id', $itemId)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if ($providerId) {
            $providerLatest = (clone $query)->where('provider_id', $providerId)->first();
            if ($providerLatest) {
                return $providerLatest;
            }
        }

        return $query->first();
    }

    public function latestUnitPrice(int $itemId, ?int $providerId = null): ?float
    {
        $latest = $this->latestForItem($itemId, $providerId);

        return $latest ? (float) $latest->unit_price : null;
    }

    /** Dernier coût d'achat enregistré (historique achats locaux / réceptions), pas le PU de vente. */
    public function latestPurchaseCost(int $itemId, ?int $providerId = null): ?float
    {
        $cost = $this->latestUnitPrice($itemId, $providerId);

        return $cost !== null ? round($cost, 2) : null;
    }

    public function defaultPurchaseCostForItem(Item $item, ?int $providerId = null): float
    {
        $lastCost = $this->latestPurchaseCost((int) $item->id, $providerId);

        return $lastCost ?? round((float) ($item->cost ?? 0), 2);
    }

    /**
     * @return Collection<int, PurchasePriceHistoryEntry>
     */
    public function historyForItem(int $itemId, int $limit = 8): Collection
    {
        $entries = collect();

        if ($this->isAvailable()) {
            $localRows = ItemPurchasePrice::query()
                ->with(['provider', 'purchaseOrder'])
                ->where('item_id', $itemId)
                ->whereNotNull('purchase_order_id')
                ->get();

            foreach ($localRows as $row) {
                $entries->push($this->mapLocalEntry($row));
            }
        }

        if ($this->isForeignAvailable()) {
            $foreignRows = ItemForeignPurchasePrice::query()
                ->with(['provider', 'foreignPurchaseOrder'])
                ->where('item_id', $itemId)
                ->get();

            foreach ($foreignRows as $row) {
                $entries->push($this->mapForeignEntry($row));
            }
        }

        return $this->deduplicateHistoryEntries($entries)
            ->sortByDesc(fn (PurchasePriceHistoryEntry $entry) => sprintf(
                '%s-%05d',
                $entry->recorded_at->format('Y-m-d H:i:s.u'),
                $entry->sort_id
            ))
            ->take($limit)
            ->values();
    }

    /**
     * Évite les doublons confirm + réception (même ligne, même prix, même qté).
     *
     * @param  Collection<int, PurchasePriceHistoryEntry>  $entries
     * @return Collection<int, PurchasePriceHistoryEntry>
     */
    private function deduplicateHistoryEntries(Collection $entries): Collection
    {
        return $entries
            ->sortByDesc(fn (PurchasePriceHistoryEntry $entry) => sprintf(
                '%s-%05d',
                $entry->recorded_at->format('Y-m-d H:i:s.u'),
                $entry->sort_id
            ))
            ->unique(fn (PurchasePriceHistoryEntry $entry) => implode('|', [
                $entry->type,
                (string) ($entry->order_id ?? ''),
                (string) ($entry->source_line_id ?? ''),
                number_format($entry->primary_amount, 4, '.', ''),
                number_format($entry->quantity, 3, '.', ''),
            ]))
            ->values();
    }

    public function latestHistoryEntryForItem(int $itemId): ?PurchasePriceHistoryEntry
    {
        return $this->historyForItem($itemId, 1)->first();
    }

    private function mapLocalEntry(ItemPurchasePrice $row): PurchasePriceHistoryEntry
    {
        return new PurchasePriceHistoryEntry(
            type: 'local',
            recorded_at: $row->recorded_at,
            quantity: (float) $row->quantity,
            provider: $row->provider,
            primary_amount: (float) $row->unit_price,
            primary_currency: 'FCFA',
            indicative_fcfa: null,
            order_id: $row->purchase_order_id ? (int) $row->purchase_order_id : null,
            order_number: $row->purchaseOrder?->order_number,
            order_route: 'tenant.purchases.show',
            sort_id: (int) $row->id,
            source_line_id: $row->purchase_line_id ? (int) $row->purchase_line_id : null,
        );
    }

    private function mapForeignEntry(ItemForeignPurchasePrice $row): PurchasePriceHistoryEntry
    {
        return new PurchasePriceHistoryEntry(
            type: 'foreign',
            recorded_at: $row->recorded_at,
            quantity: (float) $row->quantity,
            provider: $row->provider,
            primary_amount: (float) $row->unit_price_foreign,
            primary_currency: (string) $row->currency_code,
            indicative_fcfa: $row->unit_price_local !== null ? (float) $row->unit_price_local : null,
            order_id: $row->foreign_purchase_order_id ? (int) $row->foreign_purchase_order_id : null,
            order_number: $row->foreignPurchaseOrder?->order_number,
            order_route: 'tenant.foreign_purchases.show',
            sort_id: (int) $row->id,
            source_line_id: $row->foreign_purchase_line_id ? (int) $row->foreign_purchase_line_id : null,
        );
    }
}
