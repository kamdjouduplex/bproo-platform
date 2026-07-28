<?php

namespace InovCom\Stock\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Stock\Models\Product;
use InovCom\Stock\Models\StockMovement;
use InovCom\Stock\Models\Warehouse;
use InovCom\Stock\Services\StockService;
use Livewire\Component;

class StockMovementForm extends Component
{
    use AuthorizesWithTenant;

    public string $type            = 'IN';
    public string $product_id      = '0';
    public string $warehouse_id    = '0';
    public string $to_warehouse_id = '0';
    public string $quantity        = '';
    public string $reference_type  = 'adjustment';
    public string $notes           = '';

    public function mount(): void
    {
        $this->tenantAuthorize('stock.create');
    }

    protected function rules(): array
    {
        $rules = [
            'type'       => ['required', 'in:IN,OUT,TRANSFER'],
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity'   => ['required', 'numeric', 'min:0.001'],
        ];

        if ($this->type === 'TRANSFER') {
            $rules['warehouse_id']    = ['required', 'integer', 'min:1'];
            $rules['to_warehouse_id'] = ['required', 'integer', 'min:1', 'different:warehouse_id'];
        } else {
            $rules['warehouse_id']    = ['required', 'integer', 'min:1'];
            $rules['reference_type']  = ['required', 'string'];
        }

        return $rules;
    }

    public function save(StockService $stockService): void
    {
        $this->tenantAuthorize('stock.create');
        $this->validate();

        $productId   = (int) $this->product_id;
        $warehouseId = (int) $this->warehouse_id;
        $qty         = (float) $this->quantity;

        match($this->type) {
            'IN'       => $stockService->addStock(
                              $productId, $warehouseId, $qty,
                              $this->reference_type, null, $this->notes ?: null
                          ),
            'OUT'      => $stockService->removeStock(
                              $productId, $warehouseId, $qty,
                              $this->reference_type, null, $this->notes ?: null
                          ),
            'TRANSFER' => $stockService->transferStock(
                              $productId, $warehouseId,
                              (int) $this->to_warehouse_id, $qty, $this->notes ?: null
                          ),
        };

        notify()->success(__('Mouvement de stock enregistré.'));

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(route('tenant.stock.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function cancel(): void
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(route('tenant.stock.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-stock::livewire.stock.movement-form', [
            'products'       => Product::on('tenant')->active()->ordered()->get(['id', 'name', 'code', 'unit']),
            'warehouses'     => Warehouse::on('tenant')->active()->ordered()->get(['id', 'name', 'location']),
            'referenceTypes' => StockMovement::referenceTypes(),
        ])->layout('layouts.app', ['title' => 'Stock', 'subtitle' => 'Enregistrer un mouvement']);
    }
}
