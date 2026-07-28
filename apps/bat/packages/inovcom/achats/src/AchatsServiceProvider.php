<?php

namespace InovCom\Achats;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Achats\Http\Livewire\PurchaseOrderForm;
use InovCom\Achats\Http\Livewire\PurchaseOrdersIndex;
use InovCom\Achats\Http\Livewire\SupplierForm;
use InovCom\Achats\Http\Livewire\SuppliersIndex;
use InovCom\Achats\Models\PurchaseOrder;
use InovCom\Achats\Models\Supplier;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class AchatsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'achats';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-achats');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-achats-migrations');

        Livewire::component('inovcom-achats.purchase-orders-index', PurchaseOrdersIndex::class);
        Livewire::component('inovcom-achats.purchase-order-form', PurchaseOrderForm::class);
        Livewire::component('inovcom-achats.suppliers-index', SuppliersIndex::class);
        Livewire::component('inovcom-achats.supplier-form', SupplierForm::class);

        Route::bind('purchase_order', fn ($value) => PurchaseOrder::on('tenant')->findOrFail($value));
        Route::bind('supplier', fn ($value) => Supplier::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/achats', PurchaseOrdersIndex::class)
                    ->middleware(['module:achats'])
                    ->name('tenant.achats.index');
                Route::get('/achats/create', PurchaseOrderForm::class)
                    ->middleware(['module:achats'])
                    ->name('tenant.achats.create');
                Route::get('/achats/{purchase_order}/edit', PurchaseOrderForm::class)
                    ->middleware(['module:achats'])
                    ->name('tenant.achats.edit');
                Route::get('/achats/fournisseurs', SuppliersIndex::class)
                    ->middleware(['module:achats'])
                    ->name('tenant.achats.suppliers.index');
                Route::get('/achats/fournisseurs/create', SupplierForm::class)
                    ->middleware(['module:achats'])
                    ->name('tenant.achats.suppliers.create');
                Route::get('/achats/fournisseurs/{supplier}/edit', SupplierForm::class)
                    ->middleware(['module:achats'])
                    ->name('tenant.achats.suppliers.edit');
            });
    }
}
