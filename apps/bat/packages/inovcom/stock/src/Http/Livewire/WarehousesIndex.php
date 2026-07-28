<?php

namespace InovCom\Stock\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Stock\Models\StockMovement;
use InovCom\Stock\Models\Warehouse;
use Livewire\Component;

class WarehousesIndex extends Component
{
    use AuthorizesWithTenant;

    public string $search = '';

    public function mount(): void
    {
        $this->tenantAuthorize('stock.view');
    }

    public function delete(int $id): void
    {
        $this->tenantAuthorize('stock.delete');

        if (StockMovement::on('tenant')->where('warehouse_id', $id)->exists()) {
            notify()->error(__('Impossible de supprimer : cet entrepôt a des mouvements de stock.'));
            return;
        }

        Warehouse::on('tenant')->findOrFail($id)->delete();
        notify()->success(__('Entrepôt supprimé.'));
    }

    public function render()
    {
        $warehouses = Warehouse::on('tenant')
            ->when($this->search !== '', fn($q) => $q->where(function ($q) {
                $q->where('name', 'ilike', '%' . $this->search . '%')
                  ->orWhere('location', 'ilike', '%' . $this->search . '%');
            }))
            ->orderBy('name')
            ->get();

        $movementCounts = StockMovement::on('tenant')
            ->select('warehouse_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('warehouse_id')
            ->pluck('cnt', 'warehouse_id');

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-stock::livewire.warehouses.index', [
            'warehouses'     => $warehouses,
            'movementCounts' => $movementCounts,
            'tenantCode'     => $tenantCode,
            'canCreate'      => $this->tenantCan('stock.create'),
            'canEdit'        => $this->tenantCan('stock.edit'),
            'canDelete'      => $this->tenantCan('stock.delete'),
        ])->layout('layouts.app', ['title' => 'Stock', 'subtitle' => 'Entrepôts']);
    }
}
