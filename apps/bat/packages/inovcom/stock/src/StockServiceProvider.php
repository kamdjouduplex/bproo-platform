<?php

namespace InovCom\Stock;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Stock\Http\Livewire\ProductForm;
use InovCom\Stock\Http\Livewire\ProductsIndex;
use InovCom\Stock\Http\Livewire\StockDashboard;
use InovCom\Stock\Http\Livewire\StockMovementForm;
use InovCom\Stock\Http\Livewire\WarehouseForm;
use InovCom\Stock\Http\Livewire\WarehousesIndex;
use InovCom\Stock\Models\Product;
use InovCom\Stock\Models\StockMovement;
use InovCom\Stock\Models\Warehouse;
use Livewire\Livewire;

class StockServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'stock';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-stock');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-stock-migrations');

        Livewire::component('inovcom-stock.stock-dashboard',    StockDashboard::class);
        Livewire::component('inovcom-stock.products-index',     ProductsIndex::class);
        Livewire::component('inovcom-stock.product-form',       ProductForm::class);
        Livewire::component('inovcom-stock.warehouses-index',   WarehousesIndex::class);
        Livewire::component('inovcom-stock.warehouse-form',     WarehouseForm::class);
        Livewire::component('inovcom-stock.stock-movement-form', StockMovementForm::class);

        Route::bind('stock_product',   fn($v) => Product::on('tenant')->findOrFail($v));
        Route::bind('stock_warehouse', fn($v) => Warehouse::on('tenant')->findOrFail($v));
        Route::bind('stock_movement',  fn($v) => StockMovement::on('tenant')->findOrFail($v));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/stock', StockDashboard::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.index');

                Route::get('/stock/produits', ProductsIndex::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.products.index');

                Route::get('/stock/produits/create', ProductForm::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.products.create');

                Route::get('/stock/produits/{stock_product}/edit', ProductForm::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.products.edit');

                Route::get('/stock/entrepots', WarehousesIndex::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.warehouses.index');

                Route::get('/stock/entrepots/create', WarehouseForm::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.warehouses.create');

                Route::get('/stock/entrepots/{stock_warehouse}/edit', WarehouseForm::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.warehouses.edit');

                Route::get('/stock/mouvement/create', StockMovementForm::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.movements.create');
            });
    }
}
