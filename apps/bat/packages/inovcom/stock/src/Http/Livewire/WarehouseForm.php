<?php

namespace InovCom\Stock\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Stock\Models\Warehouse;
use Livewire\Component;

class WarehouseForm extends Component
{
    use AuthorizesWithTenant;

    public ?int   $warehouseId = null;
    public string $name        = '';
    public string $location    = '';
    public string $description = '';
    public bool   $is_active   = true;

    public function mount(?Warehouse $stock_warehouse = null): void
    {
        if ($stock_warehouse && $stock_warehouse->exists) {
            $this->tenantAuthorize('stock.edit');
            $this->warehouseId = $stock_warehouse->id;
            $this->name        = $stock_warehouse->name;
            $this->location    = $stock_warehouse->location ?? '';
            $this->description = $stock_warehouse->description ?? '';
            $this->is_active   = $stock_warehouse->is_active;
        } else {
            $this->tenantAuthorize('stock.create');
        }
    }

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->tenantAuthorize($this->warehouseId ? 'stock.edit' : 'stock.create');
        $this->validate();

        $data = [
            'name'        => $this->name,
            'location'    => $this->location ?: null,
            'description' => $this->description ?: null,
            'is_active'   => $this->is_active,
        ];

        if ($this->warehouseId) {
            Warehouse::on('tenant')->findOrFail($this->warehouseId)->update($data);
            notify()->success(__('Entrepôt mis à jour.'));
        } else {
            Warehouse::on('tenant')->create($data);
            notify()->success(__('Entrepôt créé.'));
        }

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(route('tenant.stock.warehouses.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function cancel(): void
    {
        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;
        $this->redirect(route('tenant.stock.warehouses.index', ['tenant' => $tenantCode]), navigate: true);
    }

    public function render()
    {
        return view('inovcom-stock::livewire.warehouses.form', [
            'isEdit' => (bool) $this->warehouseId,
        ])->layout('layouts.app', [
            'title'    => $this->warehouseId ? 'Modifier l\'entrepôt' : 'Nouvel entrepôt',
            'subtitle' => $this->name ?: '',
        ]);
    }
}
