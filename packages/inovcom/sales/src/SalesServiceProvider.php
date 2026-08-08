<?php

namespace InovCom\Sales;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Sales\Http\Controllers\SalePrintController;
use InovCom\Sales\Http\Livewire\SaleReturnForm;
use InovCom\Sales\Http\Livewire\SaleReturnShow;
use InovCom\Sales\Http\Livewire\SaleReturnsIndex;
use InovCom\Sales\Http\Livewire\SalesIndex;
use InovCom\Sales\Http\Livewire\SalesForm;
use InovCom\Sales\Models\Sale;
use InovCom\Sales\Models\SaleReturn;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class SalesServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'sales';

    public function register(): void
    {
        $this->app->singleton(\InovCom\Kernel\Contracts\SalesApi::class, \InovCom\Sales\Services\SalesApiService::class);
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-sales');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-sales-migrations');

        Livewire::component('inovcom-sales.sales-index', SalesIndex::class);
        Livewire::component('inovcom-sales.sales-form', SalesForm::class);
        Livewire::component('inovcom-sales.sale-return-form', SaleReturnForm::class);
        Livewire::component('inovcom-sales.sale-return-show', SaleReturnShow::class);
        Livewire::component('inovcom-sales.sale-returns-index', SaleReturnsIndex::class);

        Route::bind('sale', fn ($value) => Sale::on('tenant')->findOrFail($value));
        Route::bind('saleReturn', fn ($value) => SaleReturn::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/sales', SalesIndex::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.index');
                Route::get('/sales/create', SalesForm::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.create');
                Route::get('/sales/returns', SaleReturnsIndex::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.returns.index');
                Route::get('/sales/returns/{saleReturn}', SaleReturnShow::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.returns.show');
                Route::get('/sales/{sale}', SalesForm::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.show');
                Route::get('/sales/{sale}/print', SalePrintController::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.print');
                Route::get('/sales/{sale}/return', SaleReturnForm::class)
                    ->middleware(['module:sales'])
                    ->name('tenant.sales.return.create');
            });
    }
}
