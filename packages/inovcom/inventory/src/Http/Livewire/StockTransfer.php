<?php

namespace InovCom\Stock\Http\Livewire;

use App\Services\StoreContextService;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Stock\Services\StockService;
use Livewire\Component;

class StockTransfer extends Component
{
    public string $itemSearch = '';
    public array $searchResults = [];
    public ?int $selectedItemId = null;
    public ?Item $selectedItem = null;
    public string $quantity = '';
    public ?int $from_store_id = null;
    public ?int $to_store_id = null;
    public string $reason = '';

    public function mount(): void
    {
        $this->from_store_id = app(StoreContextService::class)->currentStoreId();
    }

    public function updatedItemSearch(): void
    {
        if (strlen(trim($this->itemSearch)) < 1) {
            $this->searchResults = [];
            return;
        }

        $term = trim($this->itemSearch);
        $this->searchResults = Item::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($term) . '%'])
                ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($term) . '%'])
                ->orWhereRaw('LOWER(barcode) LIKE ?', ['%' . strtolower($term) . '%']))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'barcode'])
            ->toArray();
    }

    public function selectItem(int $itemId): void
    {
        $this->selectedItem = Item::find($itemId);
        $this->selectedItemId = $this->selectedItem?->id;
        $this->itemSearch = $this->selectedItem?->name ?? '';
        $this->searchResults = [];
    }

    public function transfer(): void
    {
        $data = $this->validate([
            'selectedItemId' => 'required|integer|exists:tenant.items,id',
            'from_store_id' => 'required|integer|min:1',
            'to_store_id' => 'required|integer|min:1|different:from_store_id',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'nullable|string|max:500',
        ]);

        $service = app(StockService::class);
        $service->transferStock(
            (int) $data['selectedItemId'],
            (float) $data['quantity'],
            (int) $data['from_store_id'],
            (int) $data['to_store_id'],
            $data['reason'] ?: 'Transfert inter-boutiques',
            Auth::guard('tenant')->id()
        );

        session()->flash('success', 'Transfert de stock enregistré.');
        $this->quantity = '';
        $this->reason = '';
    }

    public function render()
    {
        $tenant = app(TenantManager::class)->tenant();
        $stores = collect();
        if ($tenant && Schema::connection('tenant')->hasTable('stores')) {
            $stores = DB::connection('tenant')->table('stores')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        return view('inovcom-stock::livewire.stock.transfer')
            ->layout('layouts.app', [
                'title' => 'Transfert de stock',
                'subtitle' => 'Transfert inter-boutiques',
            ])
            ->with(['stores' => $stores]);
    }
}
