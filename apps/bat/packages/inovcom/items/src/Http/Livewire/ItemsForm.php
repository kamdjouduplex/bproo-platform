<?php

namespace InovCom\Items\Http\Livewire;

use App\Events\ModuleEvents\ItemCreated;
use App\Events\ModuleEvents\ItemUpdated;
use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Items\Models\Category;
use InovCom\Items\Models\Item;
use InovCom\Items\Models\Unit;
use Illuminate\Support\Facades\Event;
use Livewire\Component;

class ItemsForm extends Component
{
    use AuthorizesWithTenant;

    public ?int $itemId = null;

    public string $name        = '';
    public ?string $sku        = null;
    public ?string $description = null;
    public ?int $category_id   = null;
    public ?int $unit_id       = null;
    public string $price       = '0';
    public string $cost        = '0';
    public bool $is_active     = true;

    public string $newCategoryName = '';
    public string $newUnitName     = '';
    public string $newUnitAbbr     = '';

    public function mount(?Item $item = null): void
    {
        $this->tenantAuthorize('items.view');

        if (!$item) {
            return;
        }

        $this->itemId       = $item->id;
        $this->name         = $item->name;
        $this->sku          = $item->sku;
        $this->description  = $item->description;
        $this->category_id  = $item->category_id;
        $this->unit_id      = $item->unit_id;
        $this->price        = (string) $item->price;
        $this->cost         = (string) $item->cost;
        $this->is_active    = $item->is_active;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:tenant.categories,id',
            'unit_id'     => 'nullable|exists:tenant.units,id',
            'price'       => 'required|numeric|min:0',
            'cost'        => 'required|numeric|min:0',
            'is_active'   => 'boolean',
        ]);

        $isUpdate = (bool) $this->itemId;
        $item = $this->itemId ? Item::find($this->itemId) : new Item();
        if (!$item) {
            return;
        }

        $item->fill($data);
        $item->save();

        // Dispatch event for cross-module communication
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

        $this->unit_id = $unit->id;
        $this->newUnitName = '';
        $this->newUnitAbbr = '';
    }

    public function render()
    {
        return view('inovcom-items::livewire.items.form')
            ->layout('layouts.app', [
                'title' => $this->itemId ? 'Modifier article' : 'Nouvel article',
                'subtitle' => 'Catalogue produit',
            ])
            ->with([
                'categories' => Category::orderBy('name')->get(),
                'units'      => Unit::orderBy('name')->get(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
