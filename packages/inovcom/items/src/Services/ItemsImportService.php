<?php

namespace InovCom\Items\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Batches\Models\Batch;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\ItemUnitPrice;
use InovCom\Items\Models\Unit;
use InovCom\Kernel\Contracts\BatchesApi;
use InovCom\Stock\Services\StockService;

/**
 * Import catalogue + inventaire depuis Excel/CSV.
 * Les valeurs du fichier (nom, prix, quantités) sont appliquées telles quelles.
 * La quantité = stock inventorié (absolu), prêt pour la vente.
 */
class ItemsImportService
{
    /** Canonical field => accepted header aliases (normalized). */
    private const HEADER_ALIASES = [
        'name' => ['name', 'produits', 'produit', 'designation', 'medicament', 'article', 'libelle'],
        'sku' => ['sku', 'reference', 'ref', 'code', 'code_article'],
        'quantity' => ['quantity', 'qte', 'qty', 'quantite', 'stock', 'stock_actuel', 'inventaire'],
        'cost' => ['cost', 'pu', 'p_u', 'prix_achat', 'prixachat', 'cout', 'pa'],
        'price' => ['price', 'pvu', 'p_v_u', 'pv_u', 'prix_vente', 'prixvente', 'pv'],
        'purchase_total' => ['purchase_total', 'pt', 'p_t', 'total_achat'],
        'sale_total' => ['sale_total', 'pvt', 'p_v_t', 'pv_t', 'total_vente'],
        'expiry_date' => [
            'expiry_date', 'expiry', 'date_de_p', 'date_p', 'date_peremption',
            'peremption', 'date_de_peremption', 'expire', 'dlc',
        ],
        'batch_number' => ['batch_number', 'batch', 'lot', 'n_lot', 'numero_lot'],
        'unit' => ['unit', 'unite', 'u', 'emballage'],
        'barcode' => ['barcode', 'code_barres', 'ean', 'gtin'],
    ];

    public function __construct(
        private readonly ItemsSpreadsheetReader $reader,
    ) {}

    /**
     * @return list<string>
     */
    public function templateHeaders(): array
    {
        return [
            'name',
            'sku',
            'quantity',
            'cost',
            'price',
            'expiry_date',
            'batch_number',
            'unit',
            'barcode',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templateExampleRows(): array
    {
        return [
            [
                'name' => '3FORT B/10AMP INJ',
                'sku' => '',
                'quantity' => 100,
                'cost' => 144,
                'price' => 500,
                'expiry_date' => '2027-12-31',
                'batch_number' => 'LOT-001',
                'unit' => 'Boîte',
                'barcode' => '',
            ],
            [
                'name' => '3FORT SP',
                'sku' => '',
                'quantity' => 10,
                'cost' => 1102,
                'price' => 3500,
                'expiry_date' => '2028-06-30',
                'batch_number' => 'LOT-002',
                'unit' => 'Flacon',
                'barcode' => '',
            ],
        ];
    }

    /**
     * @return array{rows: list<array<string,mixed>>, mapping: array<string,int>, errors: list<string>, warnings: list<string>}
     */
    public function parse(string $path, string $extension): array
    {
        $matrix = $this->reader->read($path, $extension);
        if ($matrix === []) {
            return ['rows' => [], 'mapping' => [], 'errors' => ['Fichier vide.'], 'warnings' => []];
        }

        $headerRow = array_shift($matrix);
        $mapping = $this->mapHeaders($headerRow);

        if (! isset($mapping['name'])) {
            return [
                'rows' => [],
                'mapping' => $mapping,
                'errors' => [
                    'Colonne obligatoire « name » (ou PRODUITS / désignation) introuvable. '
                    .'Téléchargez le modèle et conservez la première ligne d’en-têtes.',
                ],
                'warnings' => [],
            ];
        }

        $rows = [];
        $errors = [];
        $warnings = [];
        $seenNames = [];
        $seenSkus = [];

        foreach ($matrix as $index => $line) {
            $excelRow = $index + 2;
            $parsed = $this->parseDataRow($line, $mapping, $excelRow);

            if (in_array(($parsed['status'] ?? ''), ['ok', 'warning'], true)) {
                $nameKey = mb_strtolower((string) $parsed['name']);
                $skuKey = mb_strtolower((string) ($parsed['sku'] ?? ''));
                if (isset($seenNames[$nameKey])) {
                    // Même désignation = même article : lignes fusionnées à l’import (lots / qtés cumulés).
                    $parsed['status'] = 'warning';
                    $parsed['action'] = 'merge';
                    $parsed['messages'][] = 'Nom déjà présent (ligne '.$seenNames[$nameKey].') → fusion inventaire (données conservées).';
                    $warnings[] = "Ligne {$excelRow} : nom déjà présent ligne {$seenNames[$nameKey]} (fusion).";
                } else {
                    $seenNames[$nameKey] = $excelRow;
                }
                if ($skuKey !== '') {
                    if (isset($seenSkus[$skuKey])) {
                        $parsed['status'] = 'warning';
                        $parsed['action'] = 'merge';
                        $parsed['messages'][] = 'SKU déjà présent (ligne '.$seenSkus[$skuKey].') → fusion inventaire.';
                        $warnings[] = "Ligne {$excelRow} : SKU déjà présent ligne {$seenSkus[$skuKey]} (fusion).";
                    } else {
                        $seenSkus[$skuKey] = $excelRow;
                    }
                }
            }

            $rows[] = $parsed;
            if (($parsed['status'] ?? '') === 'error') {
                foreach ($parsed['messages'] as $msg) {
                    $errors[] = "Ligne {$excelRow} : {$msg}";
                }
            }
        }

        return compact('rows', 'mapping', 'errors', 'warnings');
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array{created:int, updated:int, stocked:int, skipped:int, errors:list<string>}
     */
    public function import(array $rows, ?int $userId = null, bool $includeErrors = false): array
    {
        $created = 0;
        $updated = 0;
        $stocked = 0;
        $skipped = 0;
        $errors = [];

        $importable = [];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if ($status === 'skip') {
                $skipped++;
                continue;
            }
            if ($status === 'error' && ! $includeErrors) {
                $skipped++;
                continue;
            }
            if (trim((string) ($row['name'] ?? '')) === '') {
                $skipped++;
                continue;
            }
            $importable[] = $row;
        }

        foreach ($this->groupRowsForImport($importable) as $group) {
            $primary = $group[0];
            try {
                DB::connection('tenant')->transaction(function () use ($group, $primary, $userId, &$created, &$updated, &$stocked) {
                    [$item, $wasCreated] = $this->upsertItem($primary);
                    if ($wasCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $this->applyInventoryFromRows($item, $group, $userId);
                    $stocked++;
                });
            } catch (\Throwable $e) {
                $errors[] = '« '.($primary['name'] ?? '?').' » : '.$e->getMessage();
                $skipped += count($group);
            }
        }

        return compact('created', 'updated', 'stocked', 'skipped', 'errors');
    }

    /**
     * Group duplicate designations / SKUs so one catalogue item gets all lots & summed qty.
     * Names and prices from the file are kept (first row = designation, last row wins on prices if they differ).
     *
     * @param  list<array<string,mixed>>  $rows
     * @return list<list<array<string,mixed>>>
     */
    private function groupRowsForImport(array $rows): array
    {
        $groups = [];
        $order = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = (string) ($row['name'] ?? '');
            $key = $sku !== ''
                ? 'sku:'.mb_strtolower($sku)
                : 'name:'.mb_strtolower($name);

            if (! isset($groups[$key])) {
                $groups[$key] = [];
                $order[] = $key;
            }
            $groups[$key][] = $row;
        }

        $out = [];
        foreach ($order as $key) {
            $group = $groups[$key];
            // Keep exact designation from first occurrence; use last non-empty commercial data for prices.
            $primary = $group[0];
            $last = $group[array_key_last($group)];
            $primary['price'] = $last['price'] ?? $primary['price'];
            $primary['cost'] = $last['cost'] ?? $primary['cost'];
            if (trim((string) ($last['unit'] ?? '')) !== '') {
                $primary['unit'] = $last['unit'];
            }
            if (trim((string) ($last['barcode'] ?? '')) !== '') {
                $primary['barcode'] = $last['barcode'];
            }
            $group[0] = $primary;
            $out[] = $group;
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $mapping = [];
        foreach ($headerRow as $colIndex => $raw) {
            $key = $this->normalizeHeader((string) $raw);
            if ($key === '') {
                continue;
            }
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($key, $aliases, true) && ! isset($mapping[$field])) {
                    $mapping[$field] = (int) $colIndex;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header));
        $header = str_replace(['é', 'è', 'ê', 'à', 'ù', 'ô', 'î', 'ï', 'ç'], ['e', 'e', 'e', 'a', 'u', 'o', 'i', 'i', 'c'], $header);
        $header = preg_replace('/[^a-z0-9]+/u', '_', $header) ?? '';

        return trim($header, '_');
    }

    /**
     * @param  array<int, mixed>  $line
     * @param  array<string, int>  $mapping
     * @return array<string, mixed>
     */
    private function parseDataRow(array $line, array $mapping, int $excelRow): array
    {
        $get = function (string $field) use ($line, $mapping) {
            if (! isset($mapping[$field])) {
                return null;
            }

            return $line[$mapping[$field]] ?? null;
        };

        $name = trim((string) ($get('name') ?? ''));
        $messages = [];
        $status = 'ok';

        if ($name === '') {
            return [
                'status' => 'skip',
                'excel_row' => $excelRow,
                'messages' => ['Ligne vide ignorée.'],
                'name' => '',
            ];
        }

        $quantity = $this->toFloat($get('quantity'));
        $cost = $this->toFloat($get('cost'));
        $price = $this->toFloat($get('price'));
        $sku = trim((string) ($get('sku') ?? ''));
        $unit = trim((string) ($get('unit') ?? ''));
        $barcode = trim((string) ($get('barcode') ?? ''));
        $batch = trim((string) ($get('batch_number') ?? ''));
        $expiryRaw = $get('expiry_date');
        $expiry = $this->parseDate($expiryRaw);

        if ($price === null) {
            $price = 0.0;
        }
        if ($cost === null) {
            $cost = 0.0;
        }
        if ($quantity === null) {
            $quantity = 0.0;
        }
        if ($quantity < 0) {
            $messages[] = 'Quantité négative → 0.';
            $quantity = 0.0;
            $status = 'warning';
        }
        if ($price < 0 || $cost < 0) {
            $messages[] = 'Prix négatif → 0.';
            $price = max(0.0, $price);
            $cost = max(0.0, $cost);
            $status = 'warning';
        }
        if ($expiryRaw !== null && $expiryRaw !== '' && $expiry === null) {
            $messages[] = 'Date de péremption invalide → ignorée (lot sans date stricte).';
            $status = 'warning';
        }

        $existing = $this->findExistingItem($sku, $name);
        $action = $existing ? 'update' : 'create';
        if ($existing) {
            $messages[] = 'Existant → inventaire / prix mis à jour.';
        }

        return [
            'status' => $status,
            'action' => $action,
            'excel_row' => $excelRow,
            'messages' => $messages,
            'name' => $name,
            'sku' => $sku,
            'quantity' => $quantity,
            'cost' => $cost,
            'price' => $price,
            'expiry_date' => $expiry?->format('Y-m-d'),
            'batch_number' => $batch,
            'unit' => $unit,
            'barcode' => $barcode,
            'existing_id' => $existing?->id,
        ];
    }

    private function findExistingItem(string $sku, string $name): ?Item
    {
        if ($sku !== '') {
            $bySku = Item::query()->where('sku', $sku)->first();
            if ($bySku) {
                return $bySku;
            }
        }

        return Item::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: Item, 1: bool} [item, wasCreated]
     */
    private function upsertItem(array $row): array
    {
        $existing = null;
        if (! empty($row['existing_id'])) {
            $existing = Item::query()->find((int) $row['existing_id']);
        }
        if (! $existing) {
            $existing = $this->findExistingItem((string) ($row['sku'] ?? ''), (string) $row['name']);
        }

        if ($existing) {
            $this->syncItemFromRow($existing, $row);

            return [$existing->fresh(['unitPrices']), false];
        }

        return [$this->createItem($row), true];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createItem(array $row): Item
    {
        $unit = $this->resolveUnit((string) ($row['unit'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            $sku = $this->nextSku();
        }

        $meta = $this->pharmacyOrBatchMeta((string) $row['name'], $row);

        $item = Item::query()->create([
            'name' => (string) $row['name'],
            'sku' => $sku,
            'barcode' => ($row['barcode'] ?? '') !== '' ? $row['barcode'] : null,
            'description' => null,
            'category_id' => null,
            'brand_id' => null,
            'unit_id' => $unit->id,
            'price' => (float) $row['price'],
            'cost' => (float) $row['cost'],
            'is_active' => true,
            'metadata' => $meta ?: null,
        ]);

        ItemUnitPrice::query()->create([
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'conversion_factor' => 1,
            'price' => (float) $row['price'],
            'cost' => (float) $row['cost'],
            'is_default' => true,
        ]);

        return $item->fresh(['unitPrices']);
    }

    /**
     * Keep catalogue fields aligned with the file (source of truth for this inventaire).
     *
     * @param  array<string, mixed>  $row
     */
    private function syncItemFromRow(Item $item, array $row): void
    {
        $unit = $this->resolveUnit((string) ($row['unit'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku !== '' && $sku !== $item->sku) {
            if (! Item::query()->where('sku', $sku)->where('id', '!=', $item->id)->exists()) {
                $item->sku = $sku;
            }
        }

        $meta = is_array($item->metadata) ? $item->metadata : [];
        $meta = array_merge($meta, $this->pharmacyOrBatchMeta((string) $row['name'], $row, $meta));

        $item->fill([
            'name' => (string) $row['name'],
            'barcode' => ($row['barcode'] ?? '') !== '' ? $row['barcode'] : $item->barcode,
            'unit_id' => $unit->id,
            'price' => (float) $row['price'],
            'cost' => (float) $row['cost'],
            'is_active' => true,
            'metadata' => $meta,
        ]);
        $item->save();

        $defaultPrice = $item->unitPrices()->where('is_default', true)->first()
            ?? $item->unitPrices()->orderBy('id')->first();

        if ($defaultPrice) {
            $defaultPrice->update([
                'unit_id' => $unit->id,
                'price' => (float) $row['price'],
                'cost' => (float) $row['cost'],
                'conversion_factor' => 1,
                'is_default' => true,
            ]);
        } else {
            ItemUnitPrice::query()->create([
                'item_id' => $item->id,
                'unit_id' => $unit->id,
                'conversion_factor' => 1,
                'price' => (float) $row['price'],
                'cost' => (float) $row['cost'],
                'is_default' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $existingMeta
     * @return array<string, mixed>
     */
    private function pharmacyOrBatchMeta(string $name, array $row, array $existingMeta = []): array
    {
        $meta = $existingMeta;
        $batchesAvailable = Schema::connection('tenant')->hasTable('batches');

        if (items_is_pharmacy_catalog()) {
            $meta['batch_tracked'] = true;
            $meta['requires_prescription'] = (bool) ($meta['requires_prescription'] ?? false);
            $meta['is_set'] = (bool) ($meta['is_set'] ?? false);
            // Keep provided designation intact as DCI seed (no generic placeholder overwrite if already set)
            if (empty($meta['dci'])) {
                $meta['dci'] = $name;
            }
            if (empty($meta['dosage'])) {
                $meta['dosage'] = '—';
            }
            if (empty($meta['pharma_form'])) {
                $meta['pharma_form'] = '—';
            }
        } elseif ($batchesAvailable && (
            ! empty($row['expiry_date']) || ! empty($row['batch_number']) || (float) ($row['quantity'] ?? 0) > 0
        )) {
            $meta['batch_tracked'] = true;
        }

        return $meta;
    }

    /**
     * Inventaire multi-lignes : somme des qtés + un lot par ligne (données fichier intactes).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function applyInventoryFromRows(Item $item, array $rows, ?int $userId): void
    {
        $totalQty = 0.0;
        foreach ($rows as $row) {
            $totalQty += max(0, (float) ($row['quantity'] ?? 0));
        }

        $meta = is_array($item->metadata) ? $item->metadata : [];
        $tracked = ! empty($meta['batch_tracked']) || items_is_pharmacy_catalog();
        $batchesOk = app()->bound(BatchesApi::class)
            && app(BatchesApi::class)->isAvailable()
            && class_exists(Batch::class);

        if ($batchesOk && $tracked) {
            $this->syncBatchesFromRows($item, $rows);
        }

        if (Schema::connection('tenant')->hasTable('stock_levels') && class_exists(StockService::class)) {
            app(StockService::class)->adjustStock(
                (int) $item->id,
                $totalQty,
                'Inventaire import Excel',
                $userId
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncBatchesFromRows(Item $item, array $rows): void
    {
        // Remplace l’inventaire lots de cet article par les lignes du fichier.
        Batch::query()
            ->where('item_id', $item->id)
            ->update(['quantity' => 0]);

        $usedNumbers = [];
        foreach ($rows as $index => $row) {
            $qty = max(0, (float) ($row['quantity'] ?? 0));
            $batchNumber = trim((string) ($row['batch_number'] ?? ''));
            if ($batchNumber === '') {
                $excelRow = (int) ($row['excel_row'] ?? ($index + 1));
                $batchNumber = count($rows) === 1
                    ? 'INV-'.$item->id
                    : 'INV-'.$item->id.'-'.$excelRow;
            }

            // Évite collision de n° de lot sur plusieurs lignes sans lot distinct.
            $base = $batchNumber;
            $suffix = 2;
            while (isset($usedNumbers[mb_strtolower($batchNumber)])) {
                $batchNumber = $base.'-'.$suffix;
                $suffix++;
            }
            $usedNumbers[mb_strtolower($batchNumber)] = true;

            $expiry = ! empty($row['expiry_date'])
                ? Carbon::parse((string) $row['expiry_date'])->startOfDay()
                : Carbon::parse('2099-12-31')->startOfDay();

            $batch = Batch::query()
                ->where('item_id', $item->id)
                ->where('batch_number', $batchNumber)
                ->first();

            if ($batch) {
                $batch->expiry_date = $expiry;
                $batch->quantity = $qty;
                $batch->save();
            } else {
                Batch::query()->create([
                    'item_id' => $item->id,
                    'batch_number' => $batchNumber,
                    'expiry_date' => $expiry,
                    'quantity' => $qty,
                    'received_at' => now(),
                    'reference_type' => 'items_import',
                    'reference_id' => $item->id,
                ]);
            }
        }
    }

    private function resolveUnit(string $label): Unit
    {
        $label = trim($label);
        if ($label === '') {
            $label = 'Pièce';
        }

        $existing = Unit::query()
            ->where(function ($q) use ($label) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($label)])
                    ->orWhereRaw('LOWER(abbreviation) = ?', [mb_strtolower($label)]);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $abbr = mb_strlen($label) <= 6 ? $label : mb_substr($label, 0, 6);

        return Unit::query()->create([
            'name' => $label,
            'abbreviation' => $abbr,
            'is_active' => true,
        ]);
    }

    private function nextSku(): string
    {
        $prefix = items_catalog_noun()['sku_prefix'];
        $last = Item::query()
            ->where('sku', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('sku');

        $nextNumber = 1;
        if ($last && preg_match('/'.preg_quote($prefix, '/').'-(\d+)/', (string) $last, $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        do {
            $sku = $prefix.'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Item::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $s);
        if (! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->startOfDay();
        }
        $s = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $s);

                return $dt?->startOfDay();
            } catch (\Throwable) {
                // try next
            }
        }
        try {
            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
