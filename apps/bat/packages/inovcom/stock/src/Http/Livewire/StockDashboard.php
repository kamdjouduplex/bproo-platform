<?php

namespace InovCom\Stock\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use App\Services\TenantManager;
use InovCom\Stock\Models\Product;
use InovCom\Stock\Models\StockMovement;
use InovCom\Stock\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockDashboard extends Component
{
    use AuthorizesWithTenant;

    public string $warehouseFilter = '0';

    public function mount(): void
    {
        $this->tenantAuthorize('stock.view');
    }

    public function render()
    {
        $warehouses = Warehouse::on('tenant')->active()->ordered()->get();

        $stockQuery = StockMovement::on('tenant')
            ->select('product_id', 'warehouse_id', DB::raw('SUM(quantity) as current_stock'))
            ->groupBy('product_id', 'warehouse_id')
            ->with(['product', 'warehouse'])
            ->orderBy('product_id');

        if ((int) $this->warehouseFilter > 0) {
            $stockQuery->where('warehouse_id', (int) $this->warehouseFilter);
        }

        $stockLevels = $stockQuery->get()->filter(fn($r) => (float) $r->current_stock != 0);

        $lowStockCount = $stockLevels->filter(function ($row) {
            $min = (float) ($row->product?->min_stock_alert ?? 0);
            return $min > 0 && (float) $row->current_stock < $min;
        })->count();

        $recentMovements = StockMovement::on('tenant')
            ->with(['product', 'warehouse'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $tenantCode = session('tenant_code') ?? optional(app(TenantManager::class)->tenant())->code;

        return view('inovcom-stock::livewire.stock.dashboard', [
            'warehouses'      => $warehouses,
            'stockLevels'     => $stockLevels,
            'totalProducts'   => Product::on('tenant')->active()->count(),
            'totalWarehouses' => Warehouse::on('tenant')->active()->count(),
            'lowStockCount'   => $lowStockCount,
            'recentMovements' => $recentMovements,
            'tenantCode'      => $tenantCode,
            'canCreate'       => $this->tenantCan('stock.create'),
            'canManage'       => $this->tenantCan('stock.edit'),
        ])->layout('layouts.app', ['title' => 'Stock', 'subtitle' => 'Tableau de bord']);
    }
}
