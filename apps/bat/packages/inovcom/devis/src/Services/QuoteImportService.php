<?php

namespace InovCom\Devis\Services;

/**
 * Flexible quote line import: auto-detect headers, map columns, parse rows.
 */
class QuoteImportService
{
    public const MAPPABLE_FIELDS = [
        'description',
        'quantity',
        'unit',
        'unit_price',
        'amount',
        'discount_percent',
        'cost',
        'line_type',
    ];

    /** @var array<string, list<string>> */
    private array $headerAliases = [
        'description' => [
            'designation', 'désignation', 'libelle', 'libellé', 'description', 'ouvrage',
            'poste', 'article', 'prestation', 'travaux', 'intitule', 'intitulé', 'nom',
            'produit', 'service', 'detail', 'détail', 'label',
        ],
        'quantity' => [
            'qte', 'qté', 'quantite', 'quantité', 'qty', 'nombre', 'nb', 'quantity',
        ],
        'unit' => [
            'unite', 'unité', 'u', 'um', 'unit', 'un', 'uom',
        ],
        'unit_price' => [
            'pu', 'pu ht', 'p.u.', 'prix unitaire', 'prix u', 'prix unit', 'tarif',
            'prix', 'unit price', 'puht', 'p.u ht', 'prix/u',
        ],
        'amount' => [
            'montant', 'total', 'total ht', 'montant ht', 'amount', 'prix total',
            'total ligne', 'montant ligne', 'ht',
        ],
        'discount_percent' => [
            'remise', 'remise %', 'rem %', 'discount', 'rabais', 'reduction', 'réduction',
        ],
        'cost' => [
            'cout', 'coût', 'cost', 'prix revient', 'debourse', 'déboursé', 'pa',
        ],
        'line_type' => [
            'type', 'line type', 'categorie', 'catégorie', 'nature',
        ],
    ];

    public function __construct(
        private readonly QuoteSpreadsheetReader $reader = new QuoteSpreadsheetReader(),
    ) {}

    /**
     * @return array{
     *     rows: array<int, array<int, mixed>>,
     *     suggested_header_row: int,
     *     headers: array<int, string>,
     *     mapping: array<string, int|null>
     * }
     */
    public function analyzeFile(string $path, string $extension, ?int $headerRow = null): array
    {
        $rows = $this->reader->read($path, $extension);

        if (empty($rows)) {
            throw new \RuntimeException(__('Le fichier est vide ou illisible.'));
        }

        $headerRowIndex = $headerRow !== null
            ? max(0, $headerRow - 1)
            : $this->detectHeaderRowIndex($rows);

        $headers = $this->extractHeaders($rows[$headerRowIndex] ?? []);
        $mapping = $this->suggestMapping($headers);

        return [
            'rows' => $rows,
            'suggested_header_row' => $headerRowIndex + 1,
            'headers' => $headers,
            'mapping' => $mapping,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     skipped: int,
     *     warnings: list<string>
     * }
     */
    public function buildLines(array $rows, int $headerRow, array $mapping, int $previewLimit = 50): array
    {
        $headerIndex = max(0, $headerRow - 1);
        $dataRows = array_slice($rows, $headerIndex + 1);

        $lines = [];
        $skipped = 0;
        $warnings = [];

        foreach ($dataRows as $rowIndex => $row) {
            if ($this->reader->rowIsEmpty($row)) {
                $skipped++;
                continue;
            }

            $parsed = $this->parseRow($row, $mapping);

            if ($parsed === null) {
                $skipped++;
                continue;
            }

            if ($this->isTotalRow($parsed['description'])) {
                $skipped++;
                continue;
            }

            $lines[] = $parsed;

            if ($previewLimit > 0 && count($lines) >= $previewLimit) {
                $remaining = count($dataRows) - $rowIndex - 1;
                if ($remaining > 0) {
                    $warnings[] = __(':count lignes supplémentaires seront importées.', ['count' => $remaining]);
                }
                break;
            }
        }

        return [
            'lines' => $lines,
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function countImportableLines(array $rows, int $headerRow, array $mapping): int
    {
        $result = $this->buildLines($rows, $headerRow, $mapping, 0);
        $headerIndex = max(0, $headerRow - 1);
        $dataRows = array_slice($rows, $headerIndex + 1);
        $count = 0;

        foreach ($dataRows as $row) {
            if ($this->reader->rowIsEmpty($row)) {
                continue;
            }
            $parsed = $this->parseRow($row, $mapping);
            if ($parsed === null || $this->isTotalRow($parsed['description'])) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function detectHeaderRowIndex(array $rows): int
    {
        $scanLimit = min(count($rows), 25);
        $bestIndex = 0;
        $bestScore = -1;

        for ($i = 0; $i < $scanLimit; $i++) {
            $row = $rows[$i];
            if ($this->reader->rowIsEmpty($row)) {
                continue;
            }

            $score = 0;
            foreach ($row as $cell) {
                $normalized = $this->normalizeHeader((string) $cell);
                if ($normalized === '') {
                    continue;
                }
                foreach ($this->headerAliases as $aliases) {
                    if ($this->matchesAlias($normalized, $aliases)) {
                        $score += 3;
                        break;
                    }
                }
                if (strlen($normalized) >= 2) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    private function extractHeaders(array $row): array
    {
        $headers = [];
        foreach ($row as $index => $cell) {
            $label = trim((string) $cell);
            $headers[$index] = $label !== '' ? $label : __('Colonne :n', ['n' => $index + 1]);
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int|null>
     */
    public function suggestMapping(array $headers): array
    {
        $mapping = array_fill_keys(self::MAPPABLE_FIELDS, null);
        $usedColumns = [];

        foreach (self::MAPPABLE_FIELDS as $field) {
            $aliases = $this->headerAliases[$field] ?? [];
            foreach ($headers as $index => $header) {
                if (in_array($index, $usedColumns, true)) {
                    continue;
                }
                $normalized = $this->normalizeHeader($header);
                if ($this->matchesAlias($normalized, $aliases)) {
                    $mapping[$field] = $index;
                    $usedColumns[] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int|null>  $mapping
     * @return array<string, mixed>|null
     */
    private function parseRow(array $row, array $mapping): ?array
    {
        $description = $this->cellValue($row, $mapping['description'] ?? null);
        $description = trim((string) $description);

        if ($description === '') {
            return null;
        }

        $quantityRaw = $this->parseNumber($this->cellValue($row, $mapping['quantity'] ?? null));
        $unitPriceRaw = $this->parseNumber($this->cellValue($row, $mapping['unit_price'] ?? null));
        $amountRaw = $this->parseNumber($this->cellValue($row, $mapping['amount'] ?? null));
        $unit = trim((string) ($this->cellValue($row, $mapping['unit'] ?? null) ?? ''));
        $lineTypeRaw = strtolower(trim((string) ($this->cellValue($row, $mapping['line_type'] ?? null) ?? '')));

        if ($this->isSectionTitleRow($description, $quantityRaw, $unitPriceRaw, $amountRaw, $unit, $lineTypeRaw)) {
            return $this->makeSectionLine($description);
        }

        $quantity = $quantityRaw;
        $unitPrice = $unitPriceRaw;
        $amount = $amountRaw;
        $discount = $this->parseNumber($this->cellValue($row, $mapping['discount_percent'] ?? null)) ?? 0;
        $cost = $this->parseNumber($this->cellValue($row, $mapping['cost'] ?? null)) ?? 0;

        if ($quantity === null || $quantity <= 0) {
            $quantity = 1;
        }

        if ($unitPrice === null && $amount !== null && $quantity > 0) {
            $unitPrice = round($amount / $quantity, 2);
        }

        if ($unitPrice === null) {
            $unitPrice = 0;
        }

        if ($amount === null) {
            $amount = round($quantity * $unitPrice * (1 - $discount / 100), 2);
        }

        $lineType = $this->resolveLineType($lineTypeRaw, $quantity, $unitPrice, $amount);

        return [
            'item_id' => null,
            'description' => $description,
            'quantity' => (string) $quantity,
            'unit' => $unit,
            'unit_price' => (string) $unitPrice,
            'discount_percent' => (string) $discount,
            'cost' => (string) $cost,
            'amount' => (string) $amount,
            'line_type' => $lineType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeSectionLine(string $description): array
    {
        return [
            'item_id' => null,
            'description' => $description,
            'quantity' => '0',
            'unit' => '',
            'unit_price' => '0',
            'discount_percent' => '0',
            'cost' => '0',
            'amount' => '0',
            'line_type' => 'section',
        ];
    }

    private function isSectionTitleRow(
        string $description,
        ?float $qty,
        ?float $unitPrice,
        ?float $amount,
        string $unit,
        string $lineTypeRaw = ''
    ): bool {
        if (in_array($lineTypeRaw, ['section', 'lot', 'titre', 'chapitre', 'phase', 'tranche'], true)) {
            return true;
        }

        if (preg_match('/^(lot|chapitre|section|partie|phase|tranche)\s*[\dIVXLC]+/iu', $description)) {
            return true;
        }

        if (preg_match('/^(lot|chapitre|section|partie)\s*[:—\-–]/iu', $description)) {
            return true;
        }

        $hasFinancials = ($qty !== null && $qty > 0)
            || ($unitPrice !== null && $unitPrice > 0)
            || ($amount !== null && $amount > 0)
            || $unit !== '';

        if ($hasFinancials) {
            return false;
        }

        // Ligne descriptive seule en majuscules (ex. LOT 1 — GROS ŒUVRE)
        $letters = preg_replace('/[^a-zA-ZÀ-ÿ]/u', '', $description) ?? '';
        if ($letters !== '' && mb_strtoupper($letters) === $letters && mb_strlen($description) >= 4) {
            return true;
        }

        return false;
    }

    private function resolveLineType(string $raw, float $qty, float $unitPrice, float $amount): string
    {
        $map = [
            'service' => 'service',
            'produit' => 'product',
            'product' => 'product',
            'travaux' => 'work',
            'work' => 'work',
            'ouvrage' => 'work',
            'sous-total' => 'subtotal',
            'sous total' => 'subtotal',
            'subtotal' => 'subtotal',
            'titre' => 'section',
            'lot' => 'section',
            'chapitre' => 'section',
            'section' => 'section',
            'phase' => 'section',
            'tranche' => 'section',
        ];

        if ($raw !== '' && isset($map[$raw])) {
            return $map[$raw];
        }

        if ($qty == 0 && $unitPrice == 0 && $amount == 0) {
            return 'subtotal';
        }

        return 'service';
    }

    private function isTotalRow(string $description): bool
    {
        $normalized = $this->normalizeHeader($description);

        return (bool) preg_match(
            '/^(total|sous.?total|tva|net|ttc|montant|remise|escompte|acompte|solde)\b/u',
            $normalized
        );
    }

    /**
     * @param  list<string>  $aliases
     */
    private function matchesAlias(string $header, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if ($header === $alias) {
                return true;
            }
            if (str_contains($header, $alias) || str_contains($alias, $header)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['_', '-', '.', '/'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $this->removeAccents($value);
    }

    private function removeAccents(string $value): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $transliterated !== false ? strtolower($transliterated) : $value;
    }

    private function cellValue(array $row, mixed $columnIndex): mixed
    {
        if ($columnIndex === null || $columnIndex === '') {
            return null;
        }

        $columnIndex = (int) $columnIndex;

        return $row[$columnIndex] ?? null;
    }

    public function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = trim((string) $value);
        $s = str_replace(["\xc2\xa0", ' '], '', $s);
        $s = preg_replace('/[€$£]|(xof|xfcfa|fcfa)/iu', '', $s) ?? $s;
        $s = trim($s);

        if ($s === '' || !preg_match('/[\d,.-]/', $s)) {
            return null;
        }

        // French format: 1.234,56
        if (preg_match('/,\d{1,3}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
