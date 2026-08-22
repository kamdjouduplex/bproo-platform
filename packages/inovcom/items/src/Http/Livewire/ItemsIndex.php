<?php

namespace InovCom\Items\Http\Livewire;

use App\Events\ModuleEvents\ItemDeleted;
use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use InovCom\Items\MedicamentsModule;
use InovCom\Items\Models\Brand;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Items\Services\ItemsDeleteService;
use InovCom\Items\Services\ItemsListColumnService;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemsIndex extends Component
{
    use AuthorizesItemAccess;
    use WithPagination;

    public string $search = '';

    public int $perPage = 25;

    public string $statusFilter = 'all';

    public ?int $categoryFilter = null;

    public ?int $brandFilter = null;

    public bool $showAdvancedFilters = false;

    public function mount(): void
    {
        if (function_exists('items_is_pharmacy_catalog') && items_is_pharmacy_catalog()) {
            MedicamentsModule::syncDefaultBrands();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter($value): void
    {
        $this->categoryFilter = ($value === '' || $value === null) ? null : (int) $value;
        $this->resetPage();
    }

    public function updatedBrandFilter($value): void
    {
        $this->brandFilter = ($value === '' || $value === null) ? null : (int) $value;
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->categoryFilter = null;
        $this->brandFilter = null;
        $this->showAdvancedFilters = false;
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $itemId): void
    {
        if (! $this->canItem('items.delete')) {
            notify()->error('Permission refusée.');

            return;
        }

        $item = Item::find($itemId);
        if (! $item) {
            notify()->error(ucfirst(items_catalog_noun()['singular']).' introuvable.');

            return;
        }

        try {
            app(ItemsDeleteService::class)->delete($item);
        } catch (\RuntimeException $e) {
            notify()->error($e->getMessage());

            return;
        }

        $tenant = app(TenantManager::class)->tenant();
        if ($tenant) {
            Event::dispatch(new ItemDeleted($itemId, $tenant));
        }

        notify()->success(ucfirst(items_catalog_noun()['singular']).' supprimé.');
        $this->resetPage();
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (! $this->canItem('items.view')) {
            notify()->error('Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $canViewCost = $this->canItem('items.view_cost');
        $noun = items_catalog_noun();
        $headers = [
            'Référence',
            'Désignation',
            'Catégorie',
            'Marque',
            'Unité',
            'Prix vente',
        ];
        if ($canViewCost) {
            $headers[] = 'Coût';
        }
        $headers[] = 'Code-barres';
        $headers[] = 'Statut';

        $title = ($noun['title'] ?? 'Catalogue').' — '.$this->filterLabel();
        $filename = 'catalogue-'.now()->format('Ymd_His').'.xls';
        $escape = static fn ($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

        return response()->streamDownload(function () use ($headers, $title, $escape, $canViewCost) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<h3>'.$escape($title).'</h3>';
            echo '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>'.$escape($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            $exported = 0;
            $this->baseQuery()
                ->with(['category', 'brand', 'unit'])
                ->orderBy('id')
                ->chunkById(300, function ($chunk) use (&$exported, $escape, $canViewCost) {
                    foreach ($chunk as $item) {
                        if ($exported >= 5000) {
                            return false;
                        }

                        $row = $this->mapItemRow($item, $canViewCost);
                        echo '<tr>';
                        echo '<td>'.$escape($row['sku']).'</td>';
                        echo '<td>'.$escape($row['name']).'</td>';
                        echo '<td>'.$escape($row['category']).'</td>';
                        echo '<td>'.$escape($row['brand']).'</td>';
                        echo '<td>'.$escape($row['unit']).'</td>';
                        echo '<td>'.$escape($row['price']).'</td>';
                        if ($canViewCost) {
                            echo '<td>'.$escape($row['cost']).'</td>';
                        }
                        echo '<td>'.$escape($row['barcode']).'</td>';
                        echo '<td>'.$escape($row['status']).'</td>';
                        echo '</tr>';
                        $exported++;
                    }

                    return $exported < 5000;
                }, 'items.id', 'id');

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportPdf()
    {
        if (! $this->canItem('items.view')) {
            notify()->error('Permission refusée.');

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        try {
            $canViewCost = $this->canItem('items.view_cost');
            $noun = items_catalog_noun();
            $items = $this->baseQuery()
                ->with(['category', 'brand', 'unit'])
                ->orderBy('name')
                ->limit(5000)
                ->get();

            $rows = $items->map(fn (Item $item) => $this->mapItemRow($item, $canViewCost))->all();

            $tenant = app(TenantManager::class)->tenant();
            $settings = app(TenantBrandingService::class)->documentSettings($tenant);
            $filename = 'catalogue-'.now()->format('Ymd_His').'.pdf';

            $pdf = Pdf::loadView('inovcom-items::pdf.items-list', [
                'rows' => $rows,
                'settings' => $settings,
                'shopName' => $settings['shop_name'] ?? ($tenant?->name ?? 'Bproo Pharma'),
                'title' => $noun['title'] ?? 'Catalogue',
                'filterLabel' => $this->filterLabel(),
                'canViewCost' => $canViewCost,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

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
            notify()->error('Export PDF impossible. Affinez les filtres puis réessayez.');

            return null;
        }
    }

    public function render()
    {
        $user = auth('tenant')->user();
        $columnService = app(ItemsListColumnService::class);
        $visibleColumns = $columnService->visibleColumnsForUser($user);
        $noun = items_catalog_noun();
        $isPharmacy = function_exists('items_is_pharmacy_catalog') && items_is_pharmacy_catalog();

        $categories = Schema::connection('tenant')->hasTable('categories')
            ? Category::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $brands = Schema::connection('tenant')->hasTable('brands')
            ? Brand::query()->where('is_active', true)->orderBy('name')->get()
            : collect();

        $items = $this->baseQuery()
            ->with(['category', 'brand', 'unit', 'unitPrices.unit'])
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('inovcom-items::livewire.items.index')
            ->layout('layouts.app', [
                'title' => $noun['title'],
                'subtitle' => $noun['subtitle'],
            ])
            ->with([
                'items' => $items,
                'visibleColumns' => $visibleColumns,
                'categories' => $categories,
                'brands' => $brands,
                'canViewCost' => $this->canItem('items.view_cost'),
                'canConfigureList' => $this->canItem('items.configure_list'),
                'canCreate' => $this->canItem('items.create'),
                'canUpdate' => $this->canItem('items.update'),
                'canDelete' => $this->canItem('items.delete'),
                'canExport' => $this->canItem('items.view'),
                'isPharmacyCatalog' => $isPharmacy,
                'catalogNoun' => $noun,
                'activeFiltersCount' => $this->activeFiltersCount(),
            ]);
    }

    private function mapItemRow(Item $item, bool $canViewCost): array
    {
        $row = [
            'sku' => (string) ($item->sku ?: '—'),
            'name' => (string) ($item->name ?: '—'),
            'category' => (string) ($item->category?->name ?: '—'),
            'brand' => (string) ($item->brand?->name ?: '—'),
            'unit' => (string) ($item->unit?->abbreviation ?? $item->unit?->name ?? '—'),
            'price' => fmt_money((float) $item->price),
            'barcode' => (string) ($item->barcode ?: '—'),
            'status' => $item->is_active ? 'Actif' : 'Inactif',
        ];

        if ($canViewCost) {
            $row['cost'] = fmt_money((float) $item->cost);
        }

        return $row;
    }

    private function filterLabel(): string
    {
        $parts = [];
        if ($this->search !== '') {
            $parts[] = 'Recherche : '.$this->search;
        }
        if ($this->statusFilter === 'active') {
            $parts[] = 'Statut : Actifs';
        } elseif ($this->statusFilter === 'inactive') {
            $parts[] = 'Statut : Inactifs';
        }
        if ($this->categoryFilter) {
            $parts[] = 'Catégorie : '.(Category::find($this->categoryFilter)?->name ?? '#'.$this->categoryFilter);
        }
        if ($this->brandFilter) {
            $parts[] = 'Marque : '.(Brand::find($this->brandFilter)?->name ?? '#'.$this->brandFilter);
        }

        return $parts === [] ? 'Tous les articles' : implode(' · ', $parts);
    }

    private function activeFiltersCount(): int
    {
        $count = 0;
        if ($this->categoryFilter) {
            $count++;
        }
        if ($this->brandFilter) {
            $count++;
        }

        return $count;
    }

    private function baseQuery(): Builder
    {
        return Item::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$term]);
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->brandFilter, fn ($q) => $q->where('brand_id', $this->brandFilter));
    }
}
