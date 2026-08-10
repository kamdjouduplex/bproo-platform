<?php

namespace InovCom\Items\Http\Livewire;

use App\Events\ModuleEvents\ItemCreated;
use App\Events\ModuleEvents\ItemUpdated;
use App\Services\TenantManager;
use Illuminate\Validation\Rule;
use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use InovCom\Items\Models\Brand;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\ItemUnitPrice;
use InovCom\Items\Models\Unit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Services\ItemSetService;
use InovCom\Stock\Services\StorageLocationService;
use Livewire\Component;

class ItemsForm extends Component
{
    use AuthorizesItemAccess;

    public ?int $itemId = null;

    public string $name = '';
    public ?string $sku = null;

    /** L'utilisateur souhaite saisir une référence manuelle (même si le champ est encore vide). */
    public bool $preferCustomReference = false;
    public ?string $barcode = null;
    public ?string $description = null;
    public ?int $category_id = null;
    public ?int $brand_id = null;
    public string $price = '0';
    public string $cost = '0';
    public bool $is_active = true;

    /** Pharmacy: track by batch/lot and expiry */
    public bool $batch_tracked = false;
    /** Pharmacy: requires prescription to sell */
    public bool $requires_prescription = false;

    /** Pharmacy catalogue extensions (stored in metadata) */
    public string $dci = '';
    public string $therapeutic_family = '';
    public string $pharma_form = '';
    public string $dosage = '';
    public string $manufacturer = '';
    public string $storage_temp = '';

    /** Produit vendu en lot (composition d'autres articles) */
    public bool $is_set = false;

    /** @var array<int, array{component_item_id: int|null, quantity: string}> */
    public array $set_components = [];

    /** @var array<int, array{unit_id: int|null, unit_name: string, conversion_factor: string, price: string, cost: string}> */
    public array $unit_prices = [];

    public string $newCategoryName = '';
    public string $newBrandName = '';
    public string $newUnitName = '';
    public string $newUnitAbbr = '';
    public bool $showNewCategoryForm = false;
    public bool $showNewBrandForm = false;

    /** @var array<int, array{zone: string, aisle: string, shelf: string, bin: string, is_primary: bool}> */
    public array $storage_locations = [];
    public bool $storageLocationsEnabled = false;

    public function mount(?Item $item = null): void
    {
        $this->storageLocationsEnabled = Schema::connection('tenant')->hasTable('storage_locations');

        if (!$item) {
            $this->sku = '';
            $this->unit_prices = [
                ['unit_id' => null, 'unit_name' => '', 'conversion_factor' => '1', 'price' => '0', 'cost' => '0'],
            ];
            $this->set_components = [
                ['component_item_id' => null, 'quantity' => '1'],
            ];
            if (items_is_pharmacy_catalog()) {
                $this->batch_tracked = true;
                $this->storage_temp = 'Ambiante';
            }

            return;
        }

        $this->itemId = $item->id;
        $this->name = $item->name;
        $this->sku = $item->sku;
        $this->barcode = $item->barcode;
        $this->description = $item->description;
        $this->category_id = $item->category_id;
        $this->brand_id = $item->brand_id;
        $this->price = (string) $item->price;
        $this->cost = (string) $item->cost;
        $this->is_active = $item->is_active;
        $meta = $item->metadata ?? [];
        $this->batch_tracked = (bool) ($meta['batch_tracked'] ?? false);
        $this->requires_prescription = (bool) ($meta['requires_prescription'] ?? false);
        $this->dci = (string) ($meta['dci'] ?? '');
        $this->therapeutic_family = (string) ($meta['therapeutic_family'] ?? '');
        $this->pharma_form = (string) ($meta['pharma_form'] ?? '');
        $this->dosage = (string) ($meta['dosage'] ?? '');
        $this->manufacturer = (string) ($meta['manufacturer'] ?? '');
        $this->storage_temp = (string) ($meta['storage_temp'] ?? '');
        $this->is_set = (bool) ($meta['is_set'] ?? false);

        if ($this->is_set && app(ItemSetService::class)->isAvailable()) {
            $this->set_components = app(ItemSetService::class)->components($item->id)->map(fn ($row) => [
                'component_item_id' => $row->component_item_id,
                'quantity' => fmt_num_plain($row->quantity),
            ])->toArray();
        }
        if ($this->is_set && empty($this->set_components)) {
            $this->set_components = [['component_item_id' => null, 'quantity' => '1']];
        }

        $prices = $item->unitPrices()->with('unit')->orderBy('is_default', 'desc')->orderBy('unit_id')->get();
        if ($prices->isEmpty()) {
            $this->unit_prices = [
                [
                    'unit_id' => $item->unit_id,
                    'unit_name' => $item->unit?->name ?? '',
                    'conversion_factor' => '1',
                    'price' => fmt_num_plain($item->price),
                    'cost' => fmt_num_plain($item->cost),
                ],
            ];
        } else {
            $this->unit_prices = $prices->map(fn ($p) => [
                'unit_id' => $p->unit_id,
                'unit_name' => $p->unit->name ?? '',
                'conversion_factor' => fmt_num_plain($p->conversion_factor),
                'price' => fmt_num_plain($p->price),
                'cost' => fmt_num_plain($p->cost),
            ])->toArray();
        }

        if ($this->storageLocationsEnabled) {
            $this->storage_locations = app(StorageLocationService::class)
                ->getLocationsForItem($item->id)
                ->map(fn ($loc) => [
                    'zone' => (string) $loc->zone,
                    'aisle' => (string) ($loc->aisle ?? ''),
                    'shelf' => (string) ($loc->shelf ?? ''),
                    'bin' => (string) ($loc->bin ?? ''),
                    'is_primary' => (bool) ($loc->pivot_is_primary ?? false),
                ])
                ->values()
                ->toArray();
        }
    }

    public function addStorageLocation(): void
    {
        $isFirst = count($this->storage_locations) === 0;
        $this->storage_locations[] = [
            'zone' => '',
            'aisle' => '',
            'shelf' => '',
            'bin' => '',
            'is_primary' => $isFirst,
        ];
    }

    public function removeStorageLocation(int $index): void
    {
        if (!isset($this->storage_locations[$index])) {
            return;
        }

        $wasPrimary = (bool) ($this->storage_locations[$index]['is_primary'] ?? false);
        unset($this->storage_locations[$index]);
        $this->storage_locations = array_values($this->storage_locations);

        // Garantir un emplacement principal s'il en reste.
        if ($wasPrimary && !empty($this->storage_locations)) {
            $hasPrimary = collect($this->storage_locations)->contains(fn ($r) => $r['is_primary'] ?? false);
            if (!$hasPrimary) {
                $this->storage_locations[0]['is_primary'] = true;
            }
        }
    }

    public function setPrimaryStorageLocation(int $index): void
    {
        foreach ($this->storage_locations as $i => $row) {
            $this->storage_locations[$i]['is_primary'] = ($i === $index);
        }
    }

    public function getSkuUsesAutoProperty(): bool
    {
        return !$this->preferCustomReference && trim((string) ($this->sku ?? '')) === '';
    }

    public function updatedSku(?string $value): void
    {
        if (trim((string) ($value ?? '')) !== '') {
            $this->preferCustomReference = true;
        }
    }

    public function getPreviewNextReferenceProperty(): string
    {
        return $this->peekNextReference();
    }

    public function useAutoReference(): void
    {
        $this->preferCustomReference = false;
        $this->sku = '';
        $this->resetValidation('sku');
    }

    public function useCustomReference(): void
    {
        $this->preferCustomReference = true;
        $this->resetValidation('sku');
    }

    public function addUnitPrice(): void
    {
        $this->unit_prices[] = [
            'unit_id' => null,
            'unit_name' => '',
            'conversion_factor' => '1',
            'price' => '0',
            'cost' => '0',
        ];
    }

    public function removeUnitPrice(int $index): void
    {
        if (count($this->unit_prices) <= 1) {
            return;
        }
        unset($this->unit_prices[$index]);
        $this->unit_prices = array_values($this->unit_prices);
    }

    public function addSetComponent(): void
    {
        $this->set_components[] = ['component_item_id' => null, 'quantity' => '1'];
    }

    public function removeSetComponent(int $index): void
    {
        if (count($this->set_components) <= 1) {
            return;
        }
        unset($this->set_components[$index]);
        $this->set_components = array_values($this->set_components);
    }

    public function updatedIsSet(bool $value): void
    {
        if ($value) {
            $this->batch_tracked = false;
            if (empty($this->set_components)) {
                $this->set_components = [['component_item_id' => null, 'quantity' => '1']];
            }
        }
    }

    public function save(): void
    {
        $this->unit_prices = array_values(array_filter($this->unit_prices, fn ($r) => !empty($r['unit_id'])));
        if (empty($this->unit_prices)) {
            $this->addError('unit_prices', 'Ajoutez au moins une unité de vente avec un prix.');
            return;
        }

        $canViewCost = $this->canItem('items.view_cost');
        $rules = [
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:120',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:tenant.categories,id',
            'brand_id' => 'nullable|exists:tenant.brands,id',
            'is_active' => 'boolean',
            'unit_prices' => 'required|array|min:1',
            'unit_prices.*.unit_id' => 'required|exists:tenant.units,id',
            'unit_prices.*.conversion_factor' => 'required|numeric|min:0.0001',
            'unit_prices.*.price' => 'required|numeric|min:0',
        ];

        if ($canViewCost) {
            $rules['unit_prices.*.cost'] = 'required|numeric|min:0';
        }

        if (items_is_pharmacy_catalog()) {
            $rules['dci'] = 'required|string|max:120';
            $rules['dosage'] = 'required|string|max:80';
            $rules['pharma_form'] = 'required|string|max:80';
        }

        $skuInput = trim((string) ($this->sku ?? ''));
        if ($this->itemId) {
            // Référence figée après création
        } elseif ($skuInput !== '') {
            $rules['sku'] = [
                'required',
                'string',
                'max:100',
                Rule::unique(Item::class, 'sku'),
            ];
        }

        if ($this->is_set) {
            $validComponents = array_filter($this->set_components, function ($row) {
                return !empty($row['component_item_id']) && (float) ($row['quantity'] ?? 0) > 0;
            });
            if (empty($validComponents)) {
                $this->addError('set_components', 'Ajoutez au moins un article composant au lot.');
                return;
            }
        }

        $this->validate($rules);

        if ($canViewCost) {
            foreach ($this->unit_prices as $i => $row) {
                $price = (float) ($row['price'] ?? 0);
                $cost = (float) ($row['cost'] ?? 0);
                if ($price < $cost) {
                    $this->addError(
                        "unit_prices.$i.price",
                        'Le prix de vente doit être supérieur ou égal au prix d’achat (coût).'
                    );
                }
            }
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        $first = $this->unit_prices[0];
        $baseUnitId = (int) $first['unit_id'];
        $basePrice = (float) $first['price'];

        $isUpdate = (bool) $this->itemId;
        $item = $this->itemId ? Item::find($this->itemId) : new Item();
        if (!$item) {
            return;
        }

        if ($isUpdate) {
            $reference = (string) $item->sku;
        } else {
            $reference = $skuInput !== '' ? $skuInput : $this->generateReference();
        }

        $preservedCosts = [];
        if ($isUpdate && !$canViewCost) {
            foreach ($item->unitPrices as $up) {
                $preservedCosts[$up->unit_id] = (float) $up->cost;
            }
        }

        $baseCost = $canViewCost
            ? (float) $first['cost']
            : ($isUpdate ? (float) $item->cost : 0.0);

        $meta = $item->metadata ?? [];
        $meta['batch_tracked'] = $this->is_set ? false : $this->batch_tracked;
        $meta['requires_prescription'] = $this->requires_prescription;
        $meta['is_set'] = $this->is_set;
        $meta['dci'] = trim($this->dci) !== '' ? trim($this->dci) : null;
        $meta['therapeutic_family'] = trim($this->therapeutic_family) !== '' ? trim($this->therapeutic_family) : null;
        $meta['pharma_form'] = trim($this->pharma_form) !== '' ? trim($this->pharma_form) : null;
        $meta['dosage'] = trim($this->dosage) !== '' ? trim($this->dosage) : null;
        $meta['manufacturer'] = trim($this->manufacturer) !== '' ? trim($this->manufacturer) : null;
        $meta['storage_temp'] = trim($this->storage_temp) !== '' ? trim($this->storage_temp) : null;
        $meta = array_filter($meta, fn ($v) => $v !== null && $v !== '');
        // Keep boolean false flags
        $meta['batch_tracked'] = $this->is_set ? false : $this->batch_tracked;
        $meta['requires_prescription'] = $this->requires_prescription;
        $meta['is_set'] = $this->is_set;

        $item->fill([
            'name' => $this->name,
            'sku' => $reference,
            'barcode' => $this->barcode,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'unit_id' => $baseUnitId,
            'price' => $basePrice,
            'cost' => $baseCost,
            'is_active' => $this->is_active,
            'metadata' => $meta,
        ]);
        $item->save();

        if ($this->storageLocationsEnabled) {
            app(StorageLocationService::class)->syncLocationsForItem($item->id, $this->storage_locations);
        }

        $item->unitPrices()->delete();
        foreach ($this->unit_prices as $idx => $row) {
            $unitId = (int) $row['unit_id'];
            $rowCost = $canViewCost
                ? (float) $row['cost']
                : ($preservedCosts[$unitId] ?? $baseCost);

            ItemUnitPrice::create([
                'item_id' => $item->id,
                'unit_id' => (int) $row['unit_id'],
                'conversion_factor' => (float) $row['conversion_factor'],
                'price' => (float) $row['price'],
                'cost' => $rowCost,
                'is_default' => $idx === 0,
            ]);
        }

        $setService = app(ItemSetService::class);
        if ($setService->isAvailable()) {
            try {
                if ($this->is_set) {
                    $setService->syncComponents($item->id, array_values(array_map(fn ($row) => [
                        'component_item_id' => (int) $row['component_item_id'],
                        'quantity' => (float) $row['quantity'],
                    ], array_filter($this->set_components, fn ($r) => !empty($r['component_item_id'])))));
                } else {
                    $setService->syncComponents($item->id, []);
                }
            } catch (\InvalidArgumentException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        $tenant = app(TenantManager::class)->tenant();
        if ($tenant) {
            if ($isUpdate) {
                Event::dispatch(new ItemUpdated($item, $tenant));
            } else {
                Event::dispatch(new ItemCreated($item, $tenant));
            }
        }

        $this->redirect(route('tenant.items.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function toggleNewCategoryForm(): void
    {
        $this->showNewCategoryForm = !$this->showNewCategoryForm;
        if (!$this->showNewCategoryForm) {
            $this->newCategoryName = '';
            $this->resetValidation('newCategoryName');
        }
    }

    public function toggleNewBrandForm(): void
    {
        $this->showNewBrandForm = !$this->showNewBrandForm;
        if (!$this->showNewBrandForm) {
            $this->newBrandName = '';
            $this->resetValidation('newBrandName');
        }
    }

    public function createCategory(): void
    {
        $data = $this->validate([
            'newCategoryName' => 'required|string|max:255',
        ]);

        $category = Category::create([
            'name' => $data['newCategoryName'],
            'is_active' => true,
        ]);

        $this->category_id = $category->id;
        $this->newCategoryName = '';
        $this->showNewCategoryForm = false;
    }

    public function createBrand(): void
    {
        $data = $this->validate([
            'newBrandName' => 'required|string|max:255',
        ]);

        $brand = Brand::create([
            'name' => $data['newBrandName'],
            'is_active' => true,
        ]);

        $this->brand_id = $brand->id;
        $this->newBrandName = '';
        $this->showNewBrandForm = false;
    }

    public function createUnit(): void
    {
        $data = $this->validate([
            'newUnitName' => 'required|string|max:255',
            'newUnitAbbr' => 'nullable|string|max:50',
        ]);

        $unit = Unit::create([
            'name' => $data['newUnitName'],
            'abbreviation' => $data['newUnitAbbr'] ?? null,
            'is_active' => true,
        ]);

        foreach ($this->unit_prices as $idx => &$row) {
            if (empty($row['unit_id'])) {
                $this->unit_prices[$idx]['unit_id'] = $unit->id;
                $this->unit_prices[$idx]['unit_name'] = $unit->name;
                break;
            }
        }
        $this->newUnitName = '';
        $this->newUnitAbbr = '';
    }

    public function render()
    {
        $noun = items_catalog_noun();
        $isPharmacy = items_is_pharmacy_catalog();

        return view($isPharmacy ? 'inovcom-items::livewire.items.form-pharmacy' : 'inovcom-items::livewire.items.form')
            ->layout('layouts.app', [
                'title' => $this->itemId
                    ? ('Modifier '.$noun['singular'])
                    : ($isPharmacy ? 'Nouveau médicament' : 'Nouvel article'),
                'subtitle' => $noun['subtitle'],
            ])
            ->with([
                'categories' => Category::orderBy('name')->get(),
                'brands' => Brand::orderBy('name')->get(),
                'units' => Unit::orderBy('name')->get(),
                'componentItems' => Item::query()
                    ->where('is_active', true)
                    ->when($this->itemId, fn ($q) => $q->where('id', '!=', $this->itemId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'sku', 'metadata']),
                'setServiceReady' => app(ItemSetService::class)->isAvailable(),
                'canViewCost' => $this->canItem('items.view_cost'),
                'isPharmacyCatalog' => $isPharmacy,
                'catalogNoun' => $noun,
            ]);
    }

    private function peekNextReference(): string
    {
        return $this->buildNextReference();
    }

    private function generateReference(): string
    {
        return $this->buildNextReference();
    }

    private function buildNextReference(): string
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

        return $prefix.'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
