<?php

namespace InovCom\Purchases;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Purchases\Http\Controllers\ForeignPurchasePrintController;
use InovCom\Purchases\Http\Controllers\PurchasePrintController;
use InovCom\Purchases\Http\Livewire\ForeignPurchaseForm;
use InovCom\Purchases\Http\Livewire\ForeignPurchasesIndex;
use InovCom\Purchases\Http\Livewire\ForeignReceiptForm;
use InovCom\Purchases\Http\Livewire\ForeignPurchasesShow;
use InovCom\Purchases\Http\Livewire\PurchasesIndex;
use InovCom\Purchases\Http\Livewire\PurchaseForm;
use InovCom\Purchases\Http\Livewire\PurchasesShow;
use InovCom\Purchases\Http\Livewire\ReceiptForm;
use InovCom\Purchases\Services\PurchaseDocumentNumberService;
use InovCom\Purchases\Services\PurchasePriceHistoryService;
use InovCom\Purchases\Models\ForeignPurchaseOrder;
use InovCom\Purchases\Models\PurchaseOrder;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class PurchasesServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'purchases';

    public function register(): void
    {
        $this->app->singleton(PurchaseDocumentNumberService::class);
        $this->app->singleton(PurchasePriceHistoryService::class);
        $this->app->singleton(\InovCom\Purchases\Services\PurchasesService::class);
        $this->app->singleton(\InovCom\Purchases\Services\ForeignPurchasesService::class);
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-purchases');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-purchases-migrations');

        Livewire::component('inovcom-purchases.purchases-index', PurchasesIndex::class);
        Livewire::component('inovcom-purchases.purchase-form', PurchaseForm::class);
        Livewire::component('inovcom-purchases.purchases-show', PurchasesShow::class);
        Livewire::component('inovcom-purchases.receipt-form', ReceiptForm::class);
        Livewire::component('inovcom-purchases.foreign-purchases-index', ForeignPurchasesIndex::class);
        Livewire::component('inovcom-purchases.foreign-purchase-form', ForeignPurchaseForm::class);
        Livewire::component('inovcom-purchases.foreign-purchases-show', ForeignPurchasesShow::class);
        Livewire::component('inovcom-purchases.foreign-receipt-form', ForeignReceiptForm::class);

        Route::bind('purchase', fn ($value) => PurchaseOrder::on('tenant')->findOrFail($value));
        Route::bind('foreignPurchase', fn ($value) => ForeignPurchaseOrder::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/purchases', PurchasesIndex::class)
                    ->middleware(['module:purchases'])
                    ->name('tenant.purchases.index');
                Route::get('/purchases/create', PurchaseForm::class)
                    ->middleware(['module:purchases'])
                    ->name('tenant.purchases.create');
                Route::get('/purchases/foreign', ForeignPurchasesIndex::class)
                    ->middleware(['module:foreign_purchases'])
                    ->name('tenant.foreign_purchases.index');
                Route::get('/purchases/foreign/create', ForeignPurchaseForm::class)
                    ->middleware(['module:foreign_purchases'])
                    ->name('tenant.foreign_purchases.create');
                Route::get('/purchases/foreign/{foreignPurchase}/edit', ForeignPurchaseForm::class)
                    ->middleware(['module:foreign_purchases'])
                    ->name('tenant.foreign_purchases.edit');
                Route::get('/purchases/foreign/{foreignPurchase}/print', ForeignPurchasePrintController::class)
                    ->middleware(['module:foreign_purchases'])
                    ->name('tenant.foreign_purchases.print');
                Route::get('/purchases/foreign/{foreignPurchase}/receive', ForeignReceiptForm::class)
                    ->middleware(['module:foreign_purchases'])
                    ->name('tenant.foreign_purchases.receive');
                Route::get('/purchases/foreign/{foreignPurchase}', ForeignPurchasesShow::class)
                    ->middleware(['module:foreign_purchases'])
                    ->name('tenant.foreign_purchases.show');
                Route::get('/purchases/{purchase}', PurchasesShow::class)
                    ->middleware(['module:purchases'])
                    ->name('tenant.purchases.show');
                Route::get('/purchases/{purchase}/edit', PurchaseForm::class)
                    ->middleware(['module:purchases'])
                    ->name('tenant.purchases.edit');
                Route::get('/purchases/{purchase}/receive', ReceiptForm::class)
                    ->middleware(['module:purchases'])
                    ->name('tenant.purchases.receive');
                Route::get('/purchases/{purchase}/print', PurchasePrintController::class)
                    ->middleware(['module:purchases'])
                    ->name('tenant.purchases.print');
            });
    }
}
