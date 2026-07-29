<?php

namespace InovCom\Stock\Services;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use InovCom\Items\Models\Item;
use InovCom\Stock\Models\ItemStorageLocation;
use InovCom\Stock\Models\StorageLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StorageLocationService
{
    public function isAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('storage_locations')
            && Schema::connection('tenant')->hasTable('item_storage_locations');
    }

    public function resolveStoreId(?int $storeId = null): ?int
    {
        if ($storeId) {
            return $storeId;
        }

        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();

        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }

    public function findOrCreateLocation(
        string $zone,
        ?string $aisle = null,
        ?string $shelf = null,
        ?string $bin = null,
        ?int $storeId = null
    ): StorageLocation {
        $storeId = $this->resolveStoreId($storeId);
        $zone = trim($zone);
        $aisle = $aisle !== null ? trim($aisle) : null;
        $shelf = $shelf !== null ? trim($shelf) : null;
        $bin = $bin !== null ? trim($bin) : null;

        if ($zone === '') {
            throw new \InvalidArgumentException('Le rayon est obligatoire.');
        }

        $code = StorageLocation::buildCode($zone, $aisle, $shelf, $bin);

        $query = StorageLocation::query()->where('code', $code);
        if (Schema::connection('tenant')->hasColumn('storage_locations', 'store_id')) {
            $query->where('store_id', $storeId);
        }

        $existing = $query->first();
        if ($existing) {
            if (!$existing->is_active) {
                $existing->is_active = true;
                $existing->save();
            }

            return $existing;
        }

        return StorageLocation::create([
            'store_id' => $storeId,
            'zone' => $zone,
            'aisle' => $aisle ?: null,
            'shelf' => $shelf ?: null,
            'bin' => $bin ?: null,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    public function setPrimaryLocationForItem(
        int $itemId,
        string $zone,
        ?string $aisle = null,
        ?string $shelf = null,
        ?string $bin = null,
        ?int $storeId = null
    ): ?StorageLocation {
        if (!$this->isAvailable()) {
            return null;
        }

        $zone = trim($zone);
        if ($zone === '') {
            $this->clearPrimaryLocation($itemId, $storeId);

            return null;
        }

        $storeId = $this->resolveStoreId($storeId);
        $location = $this->findOrCreateLocation($zone, $aisle, $shelf, $bin, $storeId);

        return DB::connection('tenant')->transaction(function () use ($itemId, $location, $storeId) {
            $this->clearPrimaryLocation($itemId, $storeId, keepLocationId: $location->id);

            ItemStorageLocation::updateOrCreate(
                [
                    'item_id' => $itemId,
                    'storage_location_id' => $location->id,
                ],
                ['is_primary' => true]
            );

            return $location;
        });
    }

    public function clearPrimaryLocation(int $itemId, ?int $storeId = null, ?int $keepLocationId = null): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $storeId = $this->resolveStoreId($storeId);

        $query = ItemStorageLocation::query()
            ->where('item_id', $itemId)
            ->where('is_primary', true)
            ->whereHas('storageLocation', function ($q) use ($storeId) {
                if (Schema::connection('tenant')->hasColumn('storage_locations', 'store_id') && $storeId) {
                    $q->where('store_id', $storeId);
                }
            });

        if ($keepLocationId) {
            $query->where('storage_location_id', '!=', $keepLocationId);
        }

        $query->delete();
    }

    public function getPrimaryLocation(int $itemId, ?int $storeId = null): ?StorageLocation
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $storeId = $this->resolveStoreId($storeId);

        return StorageLocation::query()
            ->whereHas('items', function ($q) use ($itemId) {
                $q->where('items.id', $itemId)->where('item_storage_locations.is_primary', true);
            })
            ->when(
                Schema::connection('tenant')->hasColumn('storage_locations', 'store_id') && $storeId,
                fn ($q) => $q->where('store_id', $storeId)
            )
            ->first();
    }

    /**
     * Tous les emplacements d'un article (pour un magasin), le principal en premier.
     *
     * @return Collection<int, StorageLocation>
     */
    public function getLocationsForItem(int $itemId, ?int $storeId = null): Collection
    {
        if (!$this->isAvailable()) {
            return collect();
        }

        $storeId = $this->resolveStoreId($storeId);
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('storage_locations', 'store_id');

        return StorageLocation::query()
            ->join('item_storage_locations as isl', 'isl.storage_location_id', '=', 'storage_locations.id')
            ->where('isl.item_id', $itemId)
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('storage_locations.store_id', $storeId))
            ->orderByDesc('isl.is_primary')
            ->orderBy('storage_locations.code')
            ->select('storage_locations.*', 'isl.is_primary as pivot_is_primary')
            ->get();
    }

    /**
     * Codes des emplacements d'un article, séparés par des virgules (principal en premier).
     */
    public function codesForItem(int $itemId, ?int $storeId = null): string
    {
        return $this->getLocationsForItem($itemId, $storeId)
            ->pluck('code')
            ->filter()
            ->implode(', ');
    }

    /**
     * Remplace l'ensemble des emplacements d'un article (pour un magasin).
     * Chaque ligne : ['zone' => ?, 'aisle' => ?, 'shelf' => ?, 'bin' => ?, 'is_primary' => bool].
     * Les lignes sans rayon sont ignorées. Un (et un seul) emplacement principal est garanti.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function syncLocationsForItem(int $itemId, array $rows, ?int $storeId = null): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $storeId = $this->resolveStoreId($storeId);
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('storage_locations', 'store_id');

        $clean = [];
        foreach ($rows as $row) {
            $zone = trim((string) ($row['zone'] ?? ''));
            if ($zone === '') {
                continue;
            }
            $clean[] = [
                'zone' => $zone,
                'aisle' => trim((string) ($row['aisle'] ?? '')) ?: null,
                'shelf' => trim((string) ($row['shelf'] ?? '')) ?: null,
                'bin' => trim((string) ($row['bin'] ?? '')) ?: null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ];
        }

        DB::connection('tenant')->transaction(function () use ($itemId, $clean, $storeId, $hasStoreColumn) {
            $scopeStore = function ($q) use ($hasStoreColumn, $storeId) {
                if ($hasStoreColumn && $storeId) {
                    $q->where('store_id', $storeId);
                }
            };

            if (empty($clean)) {
                ItemStorageLocation::query()
                    ->where('item_id', $itemId)
                    ->whereHas('storageLocation', $scopeStore)
                    ->delete();

                return;
            }

            $resolved = [];      // location_id => is_primary (bool)
            $primaryLocationId = null;
            foreach ($clean as $row) {
                $location = $this->findOrCreateLocation($row['zone'], $row['aisle'], $row['shelf'], $row['bin'], $storeId);
                // En cas de doublon, on conserve "principal" si l'une des lignes l'est.
                $resolved[$location->id] = ($resolved[$location->id] ?? false) || $row['is_primary'];
                if ($row['is_primary'] && $primaryLocationId === null) {
                    $primaryLocationId = $location->id;
                }
            }

            if ($primaryLocationId === null) {
                $primaryLocationId = array_key_first($resolved);
            }

            $keepIds = array_keys($resolved);

            // Supprimer les liens (de ce magasin) absents du nouvel ensemble.
            ItemStorageLocation::query()
                ->where('item_id', $itemId)
                ->whereNotIn('storage_location_id', $keepIds)
                ->whereHas('storageLocation', $scopeStore)
                ->delete();

            foreach ($keepIds as $locationId) {
                ItemStorageLocation::updateOrCreate(
                    ['item_id' => $itemId, 'storage_location_id' => $locationId],
                    ['is_primary' => $locationId === $primaryLocationId]
                );
            }
        });
    }

    public function getLocationSummary(int $itemId, ?int $storeId = null): array
    {
        $location = $this->getPrimaryLocation($itemId, $storeId);
        $stockService = app(StockService::class);
        $available = $stockService->getAvailableQuantity($itemId, $storeId);
        $codes = $this->codesForItem($itemId, $storeId);

        return [
            'available_qty' => $available,
            'in_stock' => $available > 0,
            'location' => $location,
            'location_code' => $location?->code,
            'location_codes' => $codes ?: null,
            'location_label' => $codes ?: $location?->code,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function searchItemsWithStock(string $term, ?int $storeId = null, bool $inStockOnly = false, int $limit = 30): Collection
    {
        if (trim($term) === '') {
            return collect();
        }

        $storeId = $this->resolveStoreId($storeId);
        $stockService = app(StockService::class);
        $search = '%' . strtolower(trim($term)) . '%';

        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($q) use ($search, $storeId) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', [$search]);

                if ($this->isAvailable()) {
                    $q->orWhereIn('id', function ($sub) use ($search, $storeId) {
                        $sub->select('item_storage_locations.item_id')
                            ->from('item_storage_locations')
                            ->join('storage_locations', 'storage_locations.id', '=', 'item_storage_locations.storage_location_id')
                            ->where(function ($l) use ($search) {
                                $l->whereRaw('LOWER(storage_locations.zone) LIKE ?', [$search])
                                    ->orWhereRaw('LOWER(storage_locations.code) LIKE ?', [$search])
                                    ->orWhereRaw('LOWER(storage_locations.aisle) LIKE ?', [$search])
                                    ->orWhereRaw('LOWER(storage_locations.shelf) LIKE ?', [$search]);
                            });
                        if (Schema::connection('tenant')->hasColumn('storage_locations', 'store_id') && $storeId) {
                            $sub->where('storage_locations.store_id', $storeId);
                        }
                    });
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $items->map(function (Item $item) use ($stockService, $storeId, $inStockOnly) {
            $available = $stockService->getAvailableQuantity($item->id, $storeId);
            if ($inStockOnly && $available <= 0) {
                return null;
            }

            $location = $this->getPrimaryLocation($item->id, $storeId);
            $codesList = $this->getLocationsForItem($item->id, $storeId)
                ->pluck('code')
                ->filter()
                ->values()
                ->all();
            $codes = implode(', ', $codesList);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'available_qty' => $available,
                'in_stock' => $available > 0,
                'location_code' => $codes ?: $location?->code,
                'location_codes' => $codes ?: null,
                'location_codes_list' => $codesList,
                'location_label' => $codes ?: $location?->code,
                'zone' => $location?->zone,
                'aisle' => $location?->aisle,
                'shelf' => $location?->shelf,
            ];
        })->filter()->values();
    }
}
