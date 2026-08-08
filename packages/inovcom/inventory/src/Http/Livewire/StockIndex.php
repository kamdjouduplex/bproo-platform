<?php

namespace InovCom\Stock\Http\Livewire;

use App\Services\StoreContextService;
use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Brand;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Stock\Services\StorageLocationService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockIndex extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var array<int, string> Statuts actifs : low_stock, in_stock, out_of_stock (vide = tous) */
    public array $statusFilters = [];

    public string $categoryId = '';

    public string $brandId = '';

    public string $providerId = '';

    public int $perPage = 20;

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'in_stock' => 'En stock',
        'low_stock' => 'Stock faible',
        'out_of_stock' => 'Rupture',
    ];

    public function mount(): void
    {
        $status = request()->query('status');
        if (is_string($status) && array_key_exists($status, self::STATUS_LABELS)) {
            $this->statusFilters = [$status];
        } elseif (is_array($status)) {
            $this->statusFilters = $this->normalizeStatusFilters($status);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilters(): void
    {
        $this->statusFilters = $this->normalizeStatusFilters($this->statusFilters);
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedBrandId(): void
    {
        $this->resetPage();
    }

    public function updatedProviderId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        $this->perPage = max(10, min(100, (int) $value));
        $this->resetPage();
    }

    public function toggleStatusFilter(string $status): void
    {
        if (!array_key_exists($status, self::STATUS_LABELS)) {
            return;
        }

        if (in_array($status, $this->statusFilters, true)) {
            $this->statusFilters = array_values(array_filter(
                $this->statusFilters,
                fn ($value) => $value !== $status
            ));
        } else {
            $this->statusFilters[] = $status;
        }

        $this->statusFilters = $this->normalizeStatusFilters($this->statusFilters);
        $this->resetPage();
    }

    public function clearFilter(string $key): void
    {
        match ($key) {
            'search' => $this->search = '',
            'category' => $this->categoryId = '',
            'brand' => $this->brandId = '',
            'provider' => $this->providerId = '',
            'status' => $this->statusFilters = [],
            default => null,
        };

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilters = [];
        $this->categoryId = '';
        $this->brandId = '';
        $this->providerId = '';
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->statusFilters = $this->normalizeStatusFilters($this->statusFilters);
        $this->resetPage();
    }

    public function exportExcel(): StreamedResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $locationService = app(StorageLocationService::class);
        $storeId = $this->resolveStoreId();
        $locationsEnabled = $locationService->isAvailable();

        $headers = ['Référence', 'Désignation', 'Stock disponible', 'Stock total', "Stock d'alerte", 'Statut'];
        if ($locationsEnabled) {
            $headers[] = 'Emplacement';
        }

        $title = 'Stock — ' . $this->filterLabel();
        if (trim($this->search) !== '') {
            $title .= ' — « ' . trim($this->search) . ' »';
        }

        $filename = 'stock-' . now()->format('Ymd_His') . '.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        return response()->streamDownload(function () use (
            $storeId,
            $locationsEnabled,
            $locationService,
            $headers,
            $title,
            $escape
        ) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            if ($title !== '') {
                echo '<h3>' . $escape($title) . '</h3>';
            }
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>' . $escape($header) . '</th>';
            }
            echo '</tr></thead><tbody>';

            $exported = 0;
            $this->buildQuery($storeId, $locationsEnabled)
                ->select([
                    'items.id',
                    'items.sku',
                    'items.name',
                    'stock_levels.quantity',
                    'stock_levels.available_quantity',
                    'stock_levels.reorder_point',
                ])
                ->reorder()
                ->orderBy('items.id')
                ->chunkById(400, function ($chunk) use (
                    &$exported,
                    $locationsEnabled,
                    $locationService,
                    $storeId,
                    $escape
                ) {
                    $codes = [];
                    if ($locationsEnabled) {
                        $codes = $locationService->codesForItems(
                            $chunk->pluck('id')->all(),
                            $storeId
                        );
                    }

                    foreach ($chunk as $item) {
                        if ($exported >= 5000) {
                            return false;
                        }

                        $available = (float) ($item->available_quantity ?? 0);
                        $total = (float) ($item->quantity ?? 0);
                        $reorderPoint = $item->reorder_point;
                        $status = $available <= 0
                            ? 'Rupture'
                            : ($reorderPoint !== null && $available <= (float) $reorderPoint ? 'Stock faible' : 'En stock');

                        echo '<tr>';
                        echo '<td>' . $escape($item->sku ?? '') . '</td>';
                        echo '<td>' . $escape($item->name ?? '') . '</td>';
                        echo '<td>' . $escape(fmt_num($available)) . '</td>';
                        echo '<td>' . $escape(fmt_num($total)) . '</td>';
                        echo '<td>' . $escape($reorderPoint !== null ? fmt_num((float) $reorderPoint) : '') . '</td>';
                        echo '<td>' . $escape($status) . '</td>';
                        if ($locationsEnabled) {
                            echo '<td>' . $escape($codes[(int) $item->id] ?? '') . '</td>';
                        }
                        echo '</tr>';
                        $exported++;
                    }

                    return $exported < 5000;
                }, 'items.id', 'id');

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf()
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $storeId = $this->resolveStoreId();
            // toBase() : objets légers, moins de mémoire qu'Eloquent sur gros catalogues.
            $items = $this->buildQuery($storeId, false)
                ->select([
                    'items.id',
                    'items.sku',
                    'items.name',
                    'stock_levels.available_quantity',
                ])
                ->limit(5000)
                ->toBase()
                ->get();

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'stock-' . now()->format('Ymd_His') . '.pdf';

            $pdf = Pdf::loadView('inovcom-stock::pdf.stock-list', [
                'items' => $items,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? 'Inov-Com',
                'bannerTitle' => 'État du stock',
                'title' => 'État du stock',
                'filterLabel' => $this->filterLabel(),
                'search' => trim($this->search),
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
                $y = $canvas->get_height() - 22;
                $canvas->page_text($x, $y, '{PAGE_NUM}/{PAGE_COUNT}', $font, $size, [0.12, 0.44, 0.55]);
            }

            $output = $dompdf->output();

            return response()->streamDownload(function () use ($output) {
                echo $output;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);
            notify()->error(
                'Export PDF impossible (trop d’articles ou mémoire serveur). '
                . 'Affinez avec catégorie, marque ou fournisseur, puis réessayez.'
            );

            return null;
        }
    }

    public function render()
    {
        $locationService = app(StorageLocationService::class);
        $storeId = $this->resolveStoreId();
        $locationsEnabled = $locationService->isAvailable();

        $items = $this->buildQuery($storeId, $locationsEnabled)->paginate($this->perPage);

        if ($locationsEnabled && $items->count() > 0) {
            $codes = $locationService->codesForItems(
                $items->getCollection()->pluck('id')->all(),
                $storeId
            );
            foreach ($items as $item) {
                $item->location_code = $codes[(int) $item->id] ?? null;
            }
        }

        $categories = $this->categoryOptions();
        $brands = $this->brandOptions();
        $providers = $this->providerOptions();
        $filterLabel = $this->filterLabelFromOptions($categories, $brands, $providers);
        $activeChips = $this->activeFilterChipsFromOptions($categories, $brands, $providers);
        $totalItems = $items->total();

        return view('inovcom-stock::livewire.stock.index')
            ->layout('layouts.app', [
                'title' => 'Stock',
                'subtitle' => sprintf(
                    '%s article(s) · %s',
                    number_format($totalItems, 0, ',', ' '),
                    $filterLabel
                ),
            ])
            ->with([
                'items' => $items,
                'locationsEnabled' => $locationsEnabled,
                'filterLabel' => $filterLabel,
                'activeChips' => $activeChips,
                'statusOptions' => self::STATUS_LABELS,
                'categories' => $categories,
                'brands' => $brands,
                'providers' => $providers,
                'providersAvailable' => $this->providersTableAvailable(),
            ]);
    }

    private function buildQuery(?int $storeId, bool $locationsEnabled)
    {
        $hasStoreColumn = Schema::connection('tenant')->hasColumn('stock_levels', 'store_id');
        $categoryId = $this->normalizedId($this->categoryId);
        $brandId = $this->normalizedId($this->brandId);
        $providerId = $this->normalizedId($this->providerId);

        $stockSub = DB::connection('tenant')
            ->table('stock_levels')
            ->select('item_id', 'quantity', 'available_quantity', 'reorder_point')
            ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('store_id', $storeId));

        return Item::query()
            ->leftJoinSub($stockSub, 'stock_levels', 'items.id', '=', 'stock_levels.item_id')
            ->select('items.*', 'stock_levels.quantity', 'stock_levels.available_quantity', 'stock_levels.reorder_point')
            ->when($categoryId, fn ($query) => $query->where('items.category_id', $categoryId))
            ->when($brandId, fn ($query) => $query->where('items.brand_id', $brandId))
            ->when($providerId, fn ($query) => $this->applyProviderFilter($query, $providerId))
            ->when($this->search !== '', function ($query) use ($locationsEnabled, $storeId) {
                $term = '%' . $this->search . '%';
                $query->where(function ($q) use ($term, $locationsEnabled, $storeId) {
                    $q->where('items.name', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term);
                    if ($locationsEnabled) {
                        $q->orWhereIn('items.id', function ($sub) use ($term, $storeId) {
                            $sub->select('item_storage_locations.item_id')
                                ->from('item_storage_locations')
                                ->join('storage_locations', 'storage_locations.id', '=', 'item_storage_locations.storage_location_id')
                                ->where(function ($l) use ($term) {
                                    $l->where('storage_locations.zone', 'like', $term)
                                        ->orWhere('storage_locations.code', 'like', $term)
                                        ->orWhere('storage_locations.aisle', 'like', $term)
                                        ->orWhere('storage_locations.shelf', 'like', $term);
                                });
                            if (Schema::connection('tenant')->hasColumn('storage_locations', 'store_id') && $storeId) {
                                $sub->where('storage_locations.store_id', $storeId);
                            }
                        });
                    }
                });
            })
            ->when($this->activeStatusFilters() !== [], fn ($query) => $this->applyStatusFilters($query, $storeId, $hasStoreColumn))
            ->orderBy('items.name');
    }

    private function applyProviderFilter($query, int $providerId): void
    {
        $query->where(function ($outer) use ($providerId) {
            $matched = false;

            if (Schema::connection('tenant')->hasTable('item_purchase_prices')) {
                $matched = true;
                $outer->orWhereExists(function ($sub) use ($providerId) {
                    $sub->select(DB::raw(1))
                        ->from('item_purchase_prices')
                        ->whereColumn('item_purchase_prices.item_id', 'items.id')
                        ->where('item_purchase_prices.provider_id', $providerId);
                });
            }

            if (
                Schema::connection('tenant')->hasTable('purchase_lines')
                && Schema::connection('tenant')->hasTable('purchase_orders')
            ) {
                $matched = true;
                $outer->orWhereExists(function ($sub) use ($providerId) {
                    $sub->select(DB::raw(1))
                        ->from('purchase_lines')
                        ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_lines.purchase_order_id')
                        ->whereColumn('purchase_lines.item_id', 'items.id')
                        ->where('purchase_orders.provider_id', $providerId);
                });
            }

            if (
                Schema::connection('tenant')->hasTable('foreign_purchase_lines')
                && Schema::connection('tenant')->hasTable('foreign_purchase_orders')
            ) {
                $matched = true;
                $outer->orWhereExists(function ($sub) use ($providerId) {
                    $sub->select(DB::raw(1))
                        ->from('foreign_purchase_lines')
                        ->join(
                            'foreign_purchase_orders',
                            'foreign_purchase_orders.id',
                            '=',
                            'foreign_purchase_lines.foreign_purchase_order_id'
                        )
                        ->whereColumn('foreign_purchase_lines.item_id', 'items.id')
                        ->where('foreign_purchase_orders.provider_id', $providerId);
                });
            }

            if (!$matched) {
                $outer->whereRaw('0 = 1');
            }
        });
    }

    private function resolveStoreId(): ?int
    {
        $context = app(StoreContextService::class);
        $tenant = app(TenantManager::class)->tenant();

        return $context->currentStoreId() ?: $context->defaultStoreId($tenant);
    }

    private function filterLabel(): string
    {
        return $this->filterLabelFromOptions(
            $this->categoryOptions(),
            $this->brandOptions(),
            $this->providerOptions()
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{id:int,name:string}>  $categories
     * @param  \Illuminate\Support\Collection<int, object{id:int,name:string}>  $brands
     * @param  \Illuminate\Support\Collection<int, object{id:int,name:string}>  $providers
     */
    private function filterLabelFromOptions($categories, $brands, $providers): string
    {
        $parts = [];

        foreach ($this->activeFilterChipsFromOptions($categories, $brands, $providers) as $chip) {
            if (($chip['key'] ?? '') === 'search') {
                continue;
            }
            $parts[] = $chip['label'];
        }

        return $parts === [] ? 'Tous les articles' : implode(' · ', $parts);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{id:int,name:string}>  $categories
     * @param  \Illuminate\Support\Collection<int, object{id:int,name:string}>  $brands
     * @param  \Illuminate\Support\Collection<int, object{id:int,name:string}>  $providers
     * @return array<int, array{key: string, label: string}>
     */
    private function activeFilterChipsFromOptions($categories, $brands, $providers): array
    {
        $chips = [];

        if (trim($this->search) !== '') {
            $chips[] = [
                'key' => 'search',
                'label' => 'Recherche : « ' . trim($this->search) . ' »',
            ];
        }

        $categoryId = $this->normalizedId($this->categoryId);
        if ($categoryId) {
            $name = optional($categories->firstWhere('id', $categoryId))->name;
            $chips[] = [
                'key' => 'category',
                'label' => 'Catégorie : ' . ($name ?: '#' . $categoryId),
            ];
        }

        $brandId = $this->normalizedId($this->brandId);
        if ($brandId) {
            $name = optional($brands->firstWhere('id', $brandId))->name;
            $chips[] = [
                'key' => 'brand',
                'label' => 'Marque : ' . ($name ?: '#' . $brandId),
            ];
        }

        $providerId = $this->normalizedId($this->providerId);
        if ($providerId) {
            $name = optional($providers->firstWhere('id', $providerId))->name;
            $chips[] = [
                'key' => 'provider',
                'label' => 'Fournisseur : ' . ($name ?: '#' . $providerId),
            ];
        }

        $activeStatuses = $this->activeStatusFilters();
        if ($activeStatuses !== []) {
            $chips[] = [
                'key' => 'status',
                'label' => implode(' + ', array_map(
                    fn (string $status) => self::STATUS_LABELS[$status],
                    $activeStatuses
                )),
            ];
        }

        return $chips;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function activeFilterChips(): array
    {
        return $this->activeFilterChipsFromOptions(
            $this->categoryOptions(),
            $this->brandOptions(),
            $this->providerOptions()
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id:int,name:string}>
     */
    private function categoryOptions()
    {
        if (!Schema::connection('tenant')->hasTable('categories')) {
            return collect();
        }

        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id:int,name:string}>
     */
    private function brandOptions()
    {
        if (!Schema::connection('tenant')->hasTable('brands')) {
            return collect();
        }

        return Brand::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id:int,name:string}>
     */
    private function providerOptions()
    {
        if (!$this->providersTableAvailable() || !class_exists(\InovCom\Providers\Models\Provider::class)) {
            return collect();
        }

        return \InovCom\Providers\Models\Provider::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function providersTableAvailable(): bool
    {
        return Schema::connection('tenant')->hasTable('providers');
    }

    private function normalizedId(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<int, string>
     */
    private function activeStatusFilters(): array
    {
        return $this->normalizeStatusFilters($this->statusFilters);
    }

    /**
     * @param  array<int, mixed>  $filters
     * @return array<int, string>
     */
    private function normalizeStatusFilters(array $filters): array
    {
        $allowed = array_keys(self::STATUS_LABELS);

        return array_values(array_unique(array_filter(
            array_map('strval', $filters),
            fn (string $status) => in_array($status, $allowed, true)
        )));
    }

    /**
     * Filtres alignés sur les badges du tableau (rupture / stock faible / en stock).
     * Plusieurs statuts = union (OU).
     */
    private function applyStatusFilters($query, ?int $storeId, bool $hasStoreColumn): void
    {
        $active = $this->activeStatusFilters();
        if ($active === []) {
            return;
        }

        $scopeStockLevel = function ($sub) use ($storeId, $hasStoreColumn) {
            $sub->from('stock_levels')
                ->whereColumn('stock_levels.item_id', 'items.id')
                ->when($hasStoreColumn && $storeId, fn ($q) => $q->where('stock_levels.store_id', $storeId));
        };

        $query->where(function ($outer) use ($active, $scopeStockLevel) {
            foreach ($active as $status) {
                $outer->orWhere(function ($query) use ($status, $scopeStockLevel) {
                    match ($status) {
                        'in_stock' => $query->whereExists(function ($sub) use ($scopeStockLevel) {
                            $scopeStockLevel($sub);
                            $sub->whereRaw('COALESCE(stock_levels.available_quantity, 0) > 0')
                                ->where(function ($q) {
                                    $q->whereNull('stock_levels.reorder_point')
                                        ->orWhereRaw('COALESCE(stock_levels.available_quantity, 0) > stock_levels.reorder_point');
                                });
                        }),
                        'low_stock' => $query->whereExists(function ($sub) use ($scopeStockLevel) {
                            $scopeStockLevel($sub);
                            $sub->whereNotNull('stock_levels.reorder_point')
                                ->whereRaw('COALESCE(stock_levels.available_quantity, 0) > 0')
                                ->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= stock_levels.reorder_point');
                        }),
                        'out_of_stock' => $query->where(function ($q) use ($scopeStockLevel) {
                            $q->whereNotExists(function ($sub) use ($scopeStockLevel) {
                                $scopeStockLevel($sub);
                            })->orWhereExists(function ($sub) use ($scopeStockLevel) {
                                $scopeStockLevel($sub);
                                $sub->whereRaw('COALESCE(stock_levels.available_quantity, 0) <= 0');
                            });
                        }),
                        default => null,
                    };
                });
            }
        });
    }
}
