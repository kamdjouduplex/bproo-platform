<?php

namespace InovCom\Items\Http\Livewire;

use App\Services\TenantManager;
use InovCom\Items\Http\Livewire\Concerns\AuthorizesItemAccess;
use Illuminate\Support\Facades\Schema;
use InovCom\Items\Models\Item;
use InovCom\Stock\Services\StockMovementService;
use Livewire\Component;

class ItemsShow extends Component
{
    use AuthorizesItemAccess;

    public Item $item;

    public function mount(Item $item): void
    {
        if (!$this->canItem('items.view')) {
            abort(403, 'Permission refusée.');
        }

        $this->item = $item->load(['category', 'brand', 'unit', 'unitPrices.unit', 'setComponents.componentItem']);
    }

    public function render()
    {
        $canViewCost = $this->canItem('items.view_cost');
        $purchasePriceHistory = collect();
        $latestMarketEntry = null;
        $showPurchaseHistory = false;

        if ($canViewCost && $this->purchasesModuleEnabled() && class_exists(\InovCom\Purchases\Services\PurchasePriceHistoryService::class)) {
            $historyService = app(\InovCom\Purchases\Services\PurchasePriceHistoryService::class);
            if ($historyService->isAvailable() || $historyService->isForeignAvailable()) {
                $showPurchaseHistory = true;
                $latestMarketEntry = $historyService->latestHistoryEntryForItem((int) $this->item->id);
                $purchasePriceHistory = $historyService->historyForItem((int) $this->item->id, 25);
            }
        }

        $stockMovements = collect();
        $showStockMovements = false;
        if (
            Schema::connection('tenant')->hasTable('stock_movements')
            && class_exists(StockMovementService::class)
            && $this->stockModuleEnabled()
        ) {
            $showStockMovements = true;
            $stockMovements = app(StockMovementService::class)->listForItem((int) $this->item->id, 30);
        }

        return view('inovcom-items::livewire.items.show')
            ->layout('layouts.app', [
                'title' => ucfirst(items_catalog_noun()['singular']).' '.item_primary_label($this->item->sku, $this->item->name, (string) $this->item->id),
                'subtitle' => item_secondary_label($this->item->sku, $this->item->name) ?? $this->item->name,
            ])
            ->with([
                'canViewCost' => $canViewCost,
                'isPharmacyCatalog' => items_is_pharmacy_catalog(),
                'catalogNoun' => items_catalog_noun(),
                'canUpdate' => $this->canItem('items.update'),
                'showPurchaseHistory' => $showPurchaseHistory,
                'latestMarketEntry' => $latestMarketEntry,
                'purchasePriceHistory' => $purchasePriceHistory,
                'showStockMovements' => $showStockMovements,
                'stockMovements' => $stockMovements,
            ]);
    }

    private function purchasesModuleEnabled(): bool
    {
        $tenant = app(TenantManager::class)->tenant()
            ?? request()->attributes->get('tenant');

        return $tenant && method_exists($tenant, 'hasModule') && $tenant->hasModule('purchases');
    }

    private function stockModuleEnabled(): bool
    {
        $tenant = app(TenantManager::class)->tenant()
            ?? request()->attributes->get('tenant');

        return $tenant && method_exists($tenant, 'hasModule') && $tenant->hasModule('stock');
    }
}
