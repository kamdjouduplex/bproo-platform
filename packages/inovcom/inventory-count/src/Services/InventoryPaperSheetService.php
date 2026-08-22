<?php

namespace InovCom\Inventory\Services;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use InovCom\Inventory\Models\StockCount;
use InovCom\Items\Models\Item;
use InovCom\Stock\Services\StockService;
use InovCom\Stock\Services\StorageLocationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Feuilles de comptage papier / Excel pour inventaire physique.
 */
class InventoryPaperSheetService
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * @return list<array{
     *     index: int,
     *     item_id: int,
     *     sku: string,
     *     name: string,
     *     barcode: string,
     *     unit: string,
     *     location: string,
     *     expected_quantity: float|null,
     *     counted_quantity: float|null,
     *     difference: float|null,
     *     notes: string
     * }>
     */
    public function buildRows(?StockCount $count = null, bool $showExpected = true, bool $includeCounted = false): array
    {
        $locations = $this->locationCodes();

        if ($count && ($count->isInProgress() || $count->isCompleted()) && $count->lines()->exists()) {
            $lines = $count->lines()
                ->with(['item.unit'])
                ->orderBy('item_id')
                ->get();

            return $lines->values()->map(function ($line, $i) use ($showExpected, $includeCounted, $locations) {
                $item = $line->item;

                return [
                    'index' => $i + 1,
                    'item_id' => (int) $line->item_id,
                    'sku' => (string) ($item?->sku ?? ''),
                    'name' => (string) ($item?->name ?? '—'),
                    'barcode' => (string) ($item?->barcode ?? ''),
                    'unit' => (string) ($item?->unit?->abbreviation ?? $item?->unit?->name ?? 'pc'),
                    'location' => (string) ($locations[(int) $line->item_id] ?? ''),
                    'expected_quantity' => $showExpected ? (float) $line->expected_quantity : null,
                    'counted_quantity' => $includeCounted && $line->counted_quantity !== null
                        ? (float) $line->counted_quantity
                        : null,
                    'difference' => $includeCounted ? (float) $line->difference : null,
                    'notes' => $includeCounted ? (string) ($line->notes ?? '') : '',
                ];
            })->all();
        }

        $items = Item::query()
            ->where('is_active', true)
            ->with('unit')
            ->orderBy('name')
            ->limit(5000)
            ->get();

        return $items->values()->map(function (Item $item, $i) use ($showExpected, $locations) {
            $qty = 0.0;
            try {
                $qty = (float) $this->stockService->getStockLevel($item->id)->quantity;
            } catch (\Throwable) {
                $qty = 0.0;
            }

            return [
                'index' => $i + 1,
                'item_id' => (int) $item->id,
                'sku' => (string) ($item->sku ?? ''),
                'name' => (string) $item->name,
                'barcode' => (string) ($item->barcode ?? ''),
                'unit' => (string) ($item->unit?->abbreviation ?? $item->unit?->name ?? 'pc'),
                'location' => (string) ($locations[(int) $item->id] ?? ''),
                'expected_quantity' => $showExpected ? $qty : null,
                'counted_quantity' => null,
                'difference' => null,
                'notes' => '',
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function streamExcel(
        array $rows,
        string $title,
        bool $showExpected,
        bool $includeCounted = false,
        ?string $filename = null
    ): StreamedResponse {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $filename = $filename ?: ('feuille-inventaire-'.now()->format('Ymd_His').'.xls');
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
        $hasLocations = collect($rows)->contains(fn ($r) => ($r['location'] ?? '') !== '');

        $headers = ['#', 'Référence', 'Désignation', 'Code-barres', 'Unité'];
        if ($hasLocations) {
            $headers[] = 'Emplacement';
        }
        if ($showExpected) {
            $headers[] = 'Stock système';
        }
        $headers[] = 'Qté comptée';
        if ($includeCounted) {
            $headers[] = 'Écart';
        }
        $headers[] = 'Notes / observations';

        return response()->streamDownload(function () use (
            $rows,
            $title,
            $headers,
            $escape,
            $hasLocations,
            $showExpected,
            $includeCounted
        ) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape($title).'</h3>';
            echo '<p>'.$escape(count($rows).' article(s) · Feuille de comptage inventaire').'</p>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>'.$escape($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>'.$escape($row['index']).'</td>';
                echo '<td>'.$escape($row['sku']).'</td>';
                echo '<td>'.$escape($row['name']).'</td>';
                echo '<td>'.$escape($row['barcode']).'</td>';
                echo '<td>'.$escape($row['unit']).'</td>';
                if ($hasLocations) {
                    echo '<td>'.$escape($row['location']).'</td>';
                }
                if ($showExpected) {
                    echo '<td>'.$escape($row['expected_quantity'] !== null ? fmt_num($row['expected_quantity']) : '').'</td>';
                }
                echo '<td>'.$escape($row['counted_quantity'] !== null ? fmt_num($row['counted_quantity']) : '').'</td>';
                if ($includeCounted) {
                    $diff = $row['difference'];
                    echo '<td>'.$escape($diff !== null ? (($diff > 0 ? '+' : '').fmt_num($diff)) : '').'</td>';
                }
                echo '<td>'.$escape($row['notes']).'</td>';
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return StreamedResponse|null
     */
    public function streamPdf(
        array $rows,
        string $title,
        bool $showExpected,
        bool $includeCounted = false,
        ?string $subtitle = null,
        ?string $filename = null
    ) {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = $filename ?: ('feuille-inventaire-'.now()->format('Ymd_His').'.pdf');
            $hasLocations = collect($rows)->contains(fn ($r) => ($r['location'] ?? '') !== '');

            $pdf = Pdf::loadView('inovcom-inventory::pdf.counting-sheet', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => $title,
                'subtitle' => $subtitle,
                'showExpected' => $showExpected,
                'includeCounted' => $includeCounted,
                'hasLocations' => $hasLocations,
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait');

            $dompdf = $pdf->getDomPDF();
            $dompdf->render();
            $canvas = $dompdf->getCanvas();
            $fontMetrics = $dompdf->getFontMetrics();
            $font = $fontMetrics->getFont('DejaVu Sans');
            if ($font) {
                $size = 8;
                $width = $fontMetrics->getTextWidth('00/00', $font, $size);
                $x = ($canvas->get_width() - $width) / 2;
                $y = $canvas->get_height() - 18;
                $canvas->page_text($x, $y, '{PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.06, 0.46, 0.43]);
            }

            $output = $dompdf->output();

            return response()->streamDownload(function () use ($output) {
                echo $output;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function locationCodes(): array
    {
        if (! class_exists(StorageLocationService::class)) {
            return [];
        }

        try {
            $service = app(StorageLocationService::class);
            if (! $service->isAvailable()) {
                return [];
            }

            $ids = Item::query()->where('is_active', true)->pluck('id')->all();
            if ($ids === []) {
                return [];
            }

            return $service->codesForItems($ids, null);
        } catch (\Throwable) {
            return [];
        }
    }
}
