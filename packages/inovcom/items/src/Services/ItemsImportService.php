<?php

namespace InovCom\Items\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\ItemUnitPrice;
use InovCom\Items\Models\Unit;
use InovCom\Kernel\Contracts\BatchesApi;

/**
 * Import catalogue rows from spreadsheet (no FX). Creates items + optional stock/lots.
 */
class ItemsImportService
{
    /** Canonical field => accepted header aliases (normalized). */
    private const HEADER_ALIASES = [
        'name' => ['name', 'produits', 'produit', 'designation', 'medicament', 'article', 'libelle'],
        'sku' => ['sku', 'reference', 'ref', 'code', 'code_article'],
        'quantity' => ['quantity', 'qte', 'qty', 'quantite', 'stock'],
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
                'expiry_date' => '',
                'batch_number' => '',
                'unit' => 'Flacon',
                'barcode' => '',
            ],
        ];
    }

    /**
     * Parse file into normalized row maps + validation status (no DB writes).
     *
     * @return array{rows: list<array<string,mixed>>, mapping: array<string,int>, errors: list<string>}
     */
    public function parse(string $path, string $extension): array
    {
        $matrix = $this->reader->read($path, $extension);
        if ($matrix === []) {
            return ['rows' => [], 'mapping' => [], 'errors' => ['Fichier vide.']];
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
            ];
        }

        $rows = [];
        $errors = [];
        foreach ($matrix as $index => $line) {
            $excelRow = $index + 2; // 1-based + header
            $parsed = $this->parseDataRow($line, $mapping, $excelRow);
            $rows[] = $parsed;
            if ($parsed['status'] === 'error') {
                foreach ($parsed['messages'] as $msg) {
                    $errors[] = "Ligne {$excelRow} : {$msg}";
                }
            }
        }

        return compact('rows', 'mapping', 'errors');
    }

    /**
     * Persist validated rows.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return array{created:int, skipped:int, stocked:int, errors:list<string>}
     */
    public function import(array $rows, ?int $userId = null): array
    {
        $created = 0;
        $skipped = 0;
        $stocked = 0;
        $errors = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'error') {
                $skipped++;
                continue;
            }
            if (($row['status'] ?? '') === 'skip') {
                $skipped++;
                continue;
            }

            try {
                DB::connection('tenant')->transaction(function () use ($row, $userId, &$created, &$stocked) {
                    $item = $this->createItem($row);
                    $created++;

                    $qty = (float) ($row['quantity'] ?? 0);
                    if ($qty > 0) {
                        $this->seedStock($item, $row, $qty, $userId);
                        $stocked++;
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = '« '.($row['name'] ?? '?').' » : '.$e->getMessage();
                $skipped++;
            }
        }

        return compact('created', 'skipped', 'stocked', 'errors');
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
            $idx = $mapping[$field];

            return $line[$idx] ?? null;
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
            $messages[] = 'Quantité négative.';
            $status = 'error';
        }
        if ($price < 0 || $cost < 0) {
            $messages[] = 'Prix négatif.';
            $status = 'error';
        }
        if ($expiryRaw !== null && $expiryRaw !== '' && $expiry === null) {
            $messages[] = 'Date de péremption invalide (utilisez AAAA-MM-JJ ou JJ/MM/AAAA).';
            $status = 'error';
        }
        if ($sku !== '' && Item::on('tenant')->where('sku', $sku)->exists()) {
            $messages[] = "Référence SKU « {$sku} » déjà utilisée.";
            $status = 'error';
        }
        if (Item::on('tenant')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            $messages[] = 'Un article avec ce nom existe déjà (ignoré si vous confirmez uniquement les lignes OK, ou corrigez).';
            // soft warning — still allow if user wants duplicate names? Better mark error to avoid duplicates
            $status = 'error';
        }

        return [
            'status' => $status,
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
        ];
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

        $meta = [];
        if (items_is_pharmacy_catalog()) {
            $meta = [
                'batch_tracked' => true,
                'requires_prescription' => false,
                'is_set' => false,
                'dci' => 'À compléter',
                'dosage' => '—',
                'pharma_form' => '—',
                'therapeutic_family' => '',
                'manufacturer' => '',
                'storage_temp' => '',
            ];
        } elseif (! empty($row['expiry_date']) || ! empty($row['batch_number']) || (float) ($row['quantity'] ?? 0) > 0) {
            $meta['batch_tracked'] = Schema::connection('tenant')->hasTable('batches');
        }

        $item = Item::on('tenant')->create([
            'name' => $row['name'],
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

        ItemUnitPrice::on('tenant')->create([
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'conversion_factor' => 1,
            'price' => (float) $row['price'],
            'cost' => (float) $row['cost'],
            'is_default' => true,
        ]);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedStock(Item $item, array $row, float $qty, ?int $userId): void
    {
        $expiry = ! empty($row['expiry_date']) ? Carbon::parse($row['expiry_date'])->startOfDay() : null;
        $batchNumber = trim((string) ($row['batch_number'] ?? ''));
        $useBatches = app()->bound(BatchesApi::class)
            && app(BatchesApi::class)->isAvailable()
            && Schema::connection('tenant')->hasTable('batches');

        $meta = is_array($item->metadata) ? $item->metadata : [];
        $tracked = ! empty($meta['batch_tracked']);

        if ($useBatches && ($tracked || $expiry || $batchNumber !== '')) {
            if ($batchNumber === '') {
                $batchNumber = 'INIT-'.$item->id.'-'.now()->format('Ymd');
            }
            if (! $expiry) {
                // Far-future placeholder when qty given without expiry (editable later)
                $expiry = Carbon::parse('2099-12-31');
            }
            app(BatchesApi::class)->recordReceipt(
                (int) $item->id,
                $batchNumber,
                $expiry,
                $qty,
                'items_import',
                (int) $item->id
            );

            return;
        }

        if (Schema::connection('tenant')->hasTable('stock_levels') && class_exists(\InovCom\Stock\Services\StockService::class)) {
            app(\InovCom\Stock\Services\StockService::class)->addStock(
                (int) $item->id,
                $qty,
                'adjustment',
                'items_import',
                (int) $item->id,
                'Import catalogue initial',
                $userId
            );
        }
    }

    private function resolveUnit(string $label): Unit
    {
        $label = trim($label);
        if ($label === '') {
            $label = 'Pièce';
        }

        $existing = Unit::on('tenant')
            ->where(function ($q) use ($label) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($label)])
                    ->orWhereRaw('LOWER(abbreviation) = ?', [mb_strtolower($label)]);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $abbr = mb_strlen($label) <= 6 ? $label : mb_substr($label, 0, 6);

        return Unit::on('tenant')->create([
            'name' => $label,
            'abbreviation' => $abbr,
            'is_active' => true,
        ]);
    }

    private function nextSku(): string
    {
        $prefix = items_catalog_noun()['sku_prefix'];
        $last = Item::on('tenant')
            ->where('sku', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('sku');

        $nextNumber = 1;
        if ($last && preg_match('/'.preg_quote($prefix, '/').'-(\d+)/', (string) $last, $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        // Avoid race collisions within same import batch
        do {
            $sku = $prefix.'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Item::on('tenant')->where('sku', $sku)->exists());

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
