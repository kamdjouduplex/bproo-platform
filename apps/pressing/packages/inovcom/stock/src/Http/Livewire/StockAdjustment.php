<?php

namespace InovCom\Stock\Http\Livewire;

use InovCom\Items\Models\Item;
use InovCom\Kernel\Contracts\ItemsApi;
use InovCom\Stock\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockAdjustment extends Component
{
    public string $itemSearch = '';
    public array $searchResults = [];
    public ?int $selectedItemId = null;
    public ?Item $selectedItem = null;
    public ?float $currentStock = null;
    public ?float $currentReorderPoint = null;
    public string $newQuantity = '';
    public string $newReorderPoint = '';
    public string $reason = '';

    public function updatedItemSearch(): void
    {
        if (!$this->canAdjust()) {
            $this->searchResults = [];
            return;
        }

        if (strlen(trim($this->itemSearch)) < 1) {
            $this->searchResults = [];
            return;
        }

        $searchTerm = trim($this->itemSearch);
        
        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($query) use ($searchTerm) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->searchResults = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
            ];
        })->toArray();
    }

    public function selectItem(array $item): void
    {
        if (!$this->canAdjust()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $this->selectedItemId = $item['id'];
        $this->selectedItem = Item::find($item['id']);
        
        $stockService = app(StockService::class);
        $stockLevel = $stockService->getStockLevel($item['id']);
        $this->currentStock = (float) $stockLevel->quantity;
        $this->currentReorderPoint = $stockLevel->reorder_point ? (float) $stockLevel->reorder_point : null;
        $this->newQuantity = (string) $this->currentStock;
        $this->newReorderPoint = $this->currentReorderPoint ? (string) $this->currentReorderPoint : '';
        
        $this->itemSearch = '';
        $this->searchResults = [];
    }

    public function clearSelection(): void
    {
        $this->selectedItemId = null;
        $this->selectedItem = null;
        $this->currentStock = null;
        $this->currentReorderPoint = null;
        $this->newQuantity = '';
        $this->newReorderPoint = '';
        $this->reason = '';
    }

    public function adjust(): void
    {
        if (!$this->canAdjust()) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        if (!$this->selectedItemId) {
            session()->flash('error', 'Veuillez sélectionner un article.');
            return;
        }

        $data = $this->validate([
            'newQuantity' => 'required|numeric|min:0',
            'newReorderPoint' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $stockService = app(StockService::class);
        $stockService->adjustStock(
            $this->selectedItemId,
            (float) $data['newQuantity'],
            $data['reason'] ?? 'Ajustement manuel'
        );

        // Update reorder point
        $stockLevel = $stockService->getStockLevel($this->selectedItemId);
        $stockLevel->reorder_point = !empty($data['newReorderPoint']) ? (float) $data['newReorderPoint'] : null;
        $stockLevel->save();

        $message = 'Stock ajusté avec succès.';
        if (!empty($data['newReorderPoint'])) {
            $message .= ' Stock d\'alerte mis à jour.';
        } elseif ($this->currentReorderPoint !== null && empty($data['newReorderPoint'])) {
            $message .= ' Stock d\'alerte supprimé.';
        }
        session()->flash('success', $message);
        $this->clearSelection();
    }

    public function mount(?int $itemId = null): void
    {
        if (!$this->canAdjust()) {
            session()->flash('error', 'Vous n\'avez pas la permission d\'ajuster le stock.');
            return;
        }

        if ($itemId) {
            $this->selectedItem = Item::find($itemId);
            if ($this->selectedItem) {
                $this->selectedItemId = $itemId;
                $stockService = app(StockService::class);
                $stockLevel = $stockService->getStockLevel($itemId);
                $this->currentStock = (float) $stockLevel->quantity;
                $this->currentReorderPoint = $stockLevel->reorder_point ? (float) $stockLevel->reorder_point : null;
                $this->newQuantity = (string) $this->currentStock;
                $this->newReorderPoint = $this->currentReorderPoint ? (string) $this->currentReorderPoint : '';
            }
        }
    }

    public function render()
    {
        $canAdjust = $this->canAdjust();

        return view('inovcom-stock::livewire.stock.adjustment')
            ->layout('layouts.app', [
                'title' => 'Ajustement de stock',
                'subtitle' => 'Modifier les niveaux de stock',
            ])
            ->with([
                'canAdjust' => $canAdjust,
            ]);
    }

    private function canAdjust(): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('stock.adjust');
    }
}
