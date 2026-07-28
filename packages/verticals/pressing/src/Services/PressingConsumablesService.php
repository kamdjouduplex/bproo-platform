<?php

namespace Pressing\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\Unit;
use InovCom\Stock\Models\StockLevel;
use InovCom\Stock\Models\StockMovement;
use InovCom\Stock\Services\StockService;
use Pressing\Models\PressingConsumableIssue;
use Pressing\Models\PressingConsumableIssueLine;
use Pressing\Models\PressingDelivery;

class PressingConsumablesService
{
    public const CATEGORY_CODE = 'consommables';

    public const CATEGORY_NAME = 'Consommables';

    public const USAGE_ATELIER = 'atelier';

    public const USAGE_LIVRAISON = 'livraison';

    /** @return list<array{sku: string, name: string, unit: string, unit_abbr: string, reorder: float, cost: float, usage: string}> */
    public static function defaultCatalog(): array
    {
        return [
            ['sku' => 'CONS-LESSIVE', 'name' => 'Lessive', 'unit' => 'Litre', 'unit_abbr' => 'L', 'reorder' => 5, 'cost' => 0, 'usage' => self::USAGE_ATELIER],
            ['sku' => 'CONS-SAVON', 'name' => 'Savon', 'unit' => 'Piece', 'unit_abbr' => 'pc', 'reorder' => 10, 'cost' => 0, 'usage' => self::USAGE_ATELIER],
            ['sku' => 'CONS-PARFUM', 'name' => 'Parfum / adoucissant', 'unit' => 'Litre', 'unit_abbr' => 'L', 'reorder' => 3, 'cost' => 0, 'usage' => self::USAGE_ATELIER],
            ['sku' => 'CONS-CINTRES', 'name' => 'Cintres', 'unit' => 'Piece', 'unit_abbr' => 'pc', 'reorder' => 50, 'cost' => 0, 'usage' => self::USAGE_LIVRAISON],
            ['sku' => 'CONS-EMBALLAGES', 'name' => 'Emballages', 'unit' => 'Piece', 'unit_abbr' => 'pc', 'reorder' => 30, 'cost' => 0, 'usage' => self::USAGE_LIVRAISON],
            ['sku' => 'CONS-ETIQUETTES', 'name' => 'Étiquettes', 'unit' => 'Piece', 'unit_abbr' => 'pc', 'reorder' => 100, 'cost' => 0, 'usage' => self::USAGE_LIVRAISON],
        ];
    }

    public function seedCatalog(): Category
    {
        $category = Category::firstOrCreate(
            ['code' => self::CATEGORY_CODE],
            ['name' => self::CATEGORY_NAME, 'description' => 'Consommables pressing', 'is_active' => true]
        );

        foreach (self::defaultCatalog() as $row) {
            $unit = Unit::firstOrCreate(
                ['abbreviation' => $row['unit_abbr']],
                ['name' => $row['unit'], 'is_active' => true]
            );

            $item = Item::firstOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'price' => 0,
                    'cost' => $row['cost'],
                    'is_active' => true,
                    'metadata' => [
                        'pressing_consumable' => true,
                        'usage' => $row['usage'],
                    ],
                ]
            );

            $meta = is_array($item->metadata) ? $item->metadata : [];
            $meta['pressing_consumable'] = true;
            $meta['usage'] = $row['usage'];
            $item->fill([
                'category_id' => $item->category_id ?: $category->id,
                'unit_id' => $item->unit_id ?: $unit->id,
                'metadata' => $meta,
            ])->save();

            $level = $this->resolveStockLevel($item->id);
            if ($level->reorder_point === null) {
                $level->reorder_point = $row['reorder'];
                $level->save();
            }
        }

        return $category;
    }

    public function resolveStockLevel(int $itemId): StockLevel
    {
        $existing = StockLevel::query()->where('item_id', $itemId)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return app(StockService::class)->getStockLevel($itemId);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return StockLevel::query()->where('item_id', $itemId)->firstOrFail();
        }
    }

    public function category(): ?Category
    {
        return Category::query()
            ->where('code', self::CATEGORY_CODE)
            ->orWhere('name', self::CATEGORY_NAME)
            ->first();
    }

    /** @return Collection<int, Item> */
    public function consumableItems(?string $usage = null): Collection
    {
        $category = $this->category();
        if (! $category) {
            return collect();
        }

        $items = Item::query()
            ->with('unit')
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($usage === null) {
            return $items;
        }

        return $items->filter(function (Item $item) use ($usage) {
            $metaUsage = (string) (($item->metadata ?? [])['usage'] ?? self::USAGE_ATELIER);

            return $metaUsage === $usage;
        })->values();
    }

    /** @return list<array<string, mixed>> */
    public function dashboardRows(?string $usage = null): array
    {
        return $this->consumableItems($usage)->map(function (Item $item) {
            $level = $this->resolveStockLevel($item->id);
            $qty = (float) $level->available_quantity;
            $reorder = $level->reorder_point !== null ? (float) $level->reorder_point : null;
            $metaUsage = (string) (($item->metadata ?? [])['usage'] ?? self::USAGE_ATELIER);

            return [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'unit' => $item->unit?->abbreviation ?? 'pc',
                'quantity' => $qty,
                'reorder_point' => $reorder,
                'is_low' => $reorder !== null && $qty <= $reorder,
                'usage' => $metaUsage,
            ];
        })->values()->all();
    }

    /**
     * Bon de sortie atelier (lessive, savon…) avec responsable + usage + rendement.
     *
     * @param  list<array{item_id:int, quantity:float|int|string}>  $lines
     */
    public function issueAtelier(array $payload, array $lines): PressingConsumableIssue
    {
        $lines = $this->normalizeLines($lines);
        if ($lines === []) {
            throw new \InvalidArgumentException(__('Ajoutez au moins un consommable avec une quantité.'));
        }

        foreach ($lines as $line) {
            $this->assertConsumable((int) $line['item_id'], self::USAGE_ATELIER);
        }

        return DB::connection('tenant')->transaction(function () use ($payload, $lines) {
            $issue = PressingConsumableIssue::create([
                'number' => $this->nextIssueNumber('ATS'),
                'type' => PressingConsumableIssue::TYPE_ATELIER,
                'order_id' => $payload['order_id'] ?? null,
                'taken_by' => $payload['taken_by'] ?? Auth::guard('tenant')->id(),
                'issued_by' => Auth::guard('tenant')->id(),
                'purpose' => $payload['purpose'] ?? 'autre',
                'work_context' => $payload['work_context'] ?? null,
                'pieces_processed' => isset($payload['pieces_processed']) ? (int) $payload['pieces_processed'] : null,
                'notes' => $payload['notes'] ?? null,
                'issued_at' => now(),
            ]);

            $this->persistLinesAndStock($issue, $lines, 'Sortie atelier '.$issue->number);

            return $issue->fresh(['lines.item', 'taker', 'order']);
        });
    }

    /**
     * Consommables de remise (cintres, emballages, étiquettes) à la livraison.
     *
     * @param  list<array{item_id:int, quantity:float|int|string}>  $lines
     */
    public function issueForDelivery(PressingDelivery $delivery, array $lines, ?int $takenBy = null): ?PressingConsumableIssue
    {
        $lines = $this->normalizeLines($lines);
        if ($lines === []) {
            return null;
        }

        foreach ($lines as $line) {
            $this->assertConsumable((int) $line['item_id'], self::USAGE_LIVRAISON);
        }

        return DB::connection('tenant')->transaction(function () use ($delivery, $lines, $takenBy) {
            $delivery->loadMissing('order');

            $issue = PressingConsumableIssue::create([
                'number' => $this->nextIssueNumber('LIV'),
                'type' => PressingConsumableIssue::TYPE_LIVRAISON,
                'order_id' => $delivery->order_id,
                'delivery_id' => $delivery->id,
                'taken_by' => $takenBy ?? Auth::guard('tenant')->id(),
                'issued_by' => Auth::guard('tenant')->id(),
                'purpose' => 'livraison',
                'work_context' => 'Remise '.$delivery->order?->number,
                'notes' => null,
                'issued_at' => now(),
            ]);

            $this->persistLinesAndStock($issue, $lines, 'Livraison '.$issue->number);

            return $issue->fresh(['lines.item', 'taker', 'order']);
        });
    }

    public function restock(int $itemId, float $quantity, ?string $reason = null): StockMovement
    {
        $this->assertConsumable($itemId);
        $this->resolveStockLevel($itemId);

        return app(StockService::class)->addStock(
            $itemId,
            $quantity,
            'in',
            'PressingConsumable',
            null,
            $reason ?: 'Entrée consommable',
        );
    }

    public function setReorderPoint(int $itemId, ?float $reorderPoint): void
    {
        $this->assertConsumable($itemId);
        $level = $this->resolveStockLevel($itemId);
        $level->reorder_point = $reorderPoint;
        $level->save();
    }

    /** @return Collection<int, PressingConsumableIssue> */
    public function recentIssues(int $limit = 20): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('pressing_consumable_issues')) {
            return collect();
        }

        return PressingConsumableIssue::query()
            ->with(['taker', 'issuer', 'order', 'lines.item'])
            ->latest('issued_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, StockMovement> */
    public function recentMovements(int $limit = 20): Collection
    {
        $ids = $this->consumableItems()->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return StockMovement::query()
            ->with(['item', 'creator'])
            ->whereIn('item_id', $ids)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function lowStockCount(): int
    {
        return collect($this->dashboardRows())->where('is_low', true)->count();
    }

    /**
     * @param  list<array{item_id:int, quantity:float|int|string}>  $lines
     * @return list<array{item_id:int, quantity:float}>
     */
    private function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }
            $out[] = ['item_id' => $itemId, 'quantity' => $qty];
        }

        return $out;
    }

    /** @param  list<array{item_id:int, quantity:float}>  $lines */
    private function persistLinesAndStock(PressingConsumableIssue $issue, array $lines, string $reason): void
    {
        $stock = app(StockService::class);

        foreach ($lines as $line) {
            $item = Item::with('unit')->find($line['item_id']);
            $level = $this->resolveStockLevel((int) $line['item_id']);
            $available = (float) $level->available_quantity;
            if ($available < (float) $line['quantity']) {
                throw new \InvalidArgumentException(
                    __('Stock insuffisant pour « :item » (dispo :available, demandé :requested).', [
                        'item' => $item?->name ?? '#'.$line['item_id'],
                        'available' => number_format($available, 2, ',', ' '),
                        'requested' => number_format((float) $line['quantity'], 2, ',', ' '),
                    ])
                );
            }

            PressingConsumableIssueLine::create([
                'issue_id' => $issue->id,
                'item_id' => $line['item_id'],
                'quantity' => $line['quantity'],
                'unit_label' => $item?->unit?->abbreviation,
            ]);

            $stock->removeStock(
                (int) $line['item_id'],
                (float) $line['quantity'],
                'out',
                PressingConsumableIssue::class,
                $issue->id,
                $reason,
            );
        }
    }

    private function nextIssueNumber(string $prefix): string
    {
        $count = PressingConsumableIssue::query()->count() + 1;

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function assertConsumable(int $itemId, ?string $expectedUsage = null): void
    {
        $category = $this->category();
        if (! $category) {
            throw new \InvalidArgumentException(__('Catégorie Consommables introuvable. Relancez l’initialisation.'));
        }

        $item = Item::query()
            ->where('id', $itemId)
            ->where('category_id', $category->id)
            ->first();

        if (! $item) {
            throw new \InvalidArgumentException(__('Article consommable introuvable.'));
        }

        if ($expectedUsage) {
            $usage = (string) (($item->metadata ?? [])['usage'] ?? self::USAGE_ATELIER);
            if ($usage !== $expectedUsage) {
                throw new \InvalidArgumentException(
                    __('« :item » n’est pas destiné à cet usage (:usage).', [
                        'item' => $item->name,
                        'usage' => $expectedUsage === self::USAGE_LIVRAISON ? __('remise') : __('atelier'),
                    ])
                );
            }
        }
    }
}
