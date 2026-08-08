<?php

namespace InovCom\Stock;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Stock\Http\Livewire\StockAdjustment;
use InovCom\Stock\Http\Livewire\StockIndex;
use InovCom\Stock\Http\Livewire\StockLookup;
use InovCom\Stock\Http\Livewire\StockMovementsIndex;
use InovCom\Stock\Http\Livewire\StockTransfer;
use InovCom\Kernel\Contracts\StockApi;
use InovCom\Stock\Adapters\InventoryStockApiAdapter;
use InovCom\Stock\Services\StockMovementService;
use InovCom\Stock\Services\StorageLocationService;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class StockServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'stock';

    public function register(): void
    {
        // Register StockService as singleton
        $this->app->singleton(\InovCom\Stock\Services\StockService::class);
        $this->app->singleton(StorageLocationService::class);
        $this->app->singleton(StockMovementService::class);
        $this->app->singleton(StockApi::class, InventoryStockApiAdapter::class);
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-stock');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-stock-migrations');

        Livewire::component('inovcom-stock.stock-index', StockIndex::class);
        Livewire::component('inovcom-stock.stock-adjustment', StockAdjustment::class);
        Livewire::component('inovcom-stock.stock-transfer', StockTransfer::class);
        Livewire::component('inovcom-stock.stock-lookup', StockLookup::class);
        Livewire::component('inovcom-stock.stock-movements-index', StockMovementsIndex::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/stock', StockIndex::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.index');
                Route::get('/stock/adjust', StockAdjustment::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.adjust');
                Route::get('/stock/adjust/item/{itemId}', StockAdjustment::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.adjust.item');
                Route::get('/stock/transfer', StockTransfer::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.transfer');
                Route::get('/stock/lookup', StockLookup::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.lookup');
                Route::get('/stock/movements', StockMovementsIndex::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.movements');
                Route::get('/stock/movements/item/{itemId}', StockMovementsIndex::class)
                    ->middleware(['module:stock'])
                    ->name('tenant.stock.movements.item');
            });
    }
}
