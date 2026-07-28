<?php

namespace InovCom\Stock\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Stock\Models\Product;
use InovCom\Stock\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithPagination, AuthorizesWithTenant;

    public string $search         = '';
    public string $categoryFilter = '';

    public function mount(): void
    {
        $this->tenantAuthorize('stock.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('stock.delete');
        $product = Product::on('tenant')->findOrFail($id);

        if (StockMovement::on('tenant')->where('product_id', $id)->exists()) {
            notify()->error(__('Impossible de supprimer : ce produit a des mouvements de stock.'));
            return;
        }

        $product->delete();
        notify()->success(__('Produit supprimé.'));
    }

    public function render()
    {
        $stockTotals = StockMovement::on('tenant')
            ->select('product_id', DB::raw('SUM(quantity) as total_stock'))
            ->groupBy('product_id')
            ->pluck('total_stock', 'product_id');

        $products = Product::on('tenant')
            ->when($this->search !== '', fn($q) => $q->where(function ($q) {
                $q->where('name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('code', 'ilike', '%' . $this->search . '%');
            }))
            ->when($this->categoryFilter !== '', fn($q) => $q->where('category', $this->categoryFilter))
            ->orderBy('name')
            ->paginate(15);

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-stock::livewire.products.index', [
            'products'    => $products,
            'stockTotals' => $stockTotals,
            'categories'  => Product::categories(),
            'tenantCode'  => $tenantCode,
            'canCreate'   => $this->tenantCan('stock.create'),
            'canEdit'     => $this->tenantCan('stock.edit'),
            'canDelete'   => $this->tenantCan('stock.delete'),
        ])->layout('layouts.app', ['title' => 'Stock', 'subtitle' => 'Produits']);
    }
}
