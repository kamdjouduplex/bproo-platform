<?php

namespace InovCom\Inventory;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Inventory\Http\Livewire\InventoryForm;
use InovCom\Inventory\Http\Livewire\InventoryIndex;
use InovCom\Inventory\Models\StockCount;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class InventoryServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'inventory';

    public function register(): void
    {
        // Register services if needed
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-inventory');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-inventory-migrations');

        Livewire::component('inovcom-inventory.inventory-index', InventoryIndex::class);
        Livewire::component('inovcom-inventory.inventory-form', InventoryForm::class);

        Route::bind('stock_count', fn ($value) => StockCount::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/inventory', InventoryIndex::class)
                    ->middleware(['module:inventory'])
                    ->name('tenant.inventory.index');
                Route::get('/inventory/create', InventoryForm::class)
                    ->middleware(['module:inventory'])
                    ->name('tenant.inventory.create');
                Route::get('/inventory/{stock_count}/edit', InventoryForm::class)
                    ->middleware(['module:inventory'])
                    ->name('tenant.inventory.edit');
            });
    }
}
