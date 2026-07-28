<?php

namespace InovCom\Stock\Http\Livewire;

use InovCom\Stock\Services\StorageLocationService;
use Livewire\Component;

class StockLookup extends Component
{
    public string $search = '';
    public bool $inStockOnly = false;

    public function updatedSearch(): void
    {
        // Live search on each keystroke
    }

    public function render()
    {
        $service = app(StorageLocationService::class);
        $results = collect();

        if (strlen(trim($this->search)) >= 1 && $service->isAvailable()) {
            $results = $service->searchItemsWithStock(
                $this->search,
                inStockOnly: $this->inStockOnly,
                limit: 50
            );
        }

        return view('inovcom-stock::livewire.stock.lookup')
            ->layout('layouts.app', [
                'title' => 'Où est le produit ?',
                'subtitle' => 'Stock et emplacement en magasin',
            ])
            ->with(['results' => $results, 'locationsEnabled' => $service->isAvailable()]);
    }
}
