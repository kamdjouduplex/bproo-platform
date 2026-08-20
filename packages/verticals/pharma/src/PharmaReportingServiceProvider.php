<?php

namespace Pharma;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;
use Pharma\Http\Controllers\PharmaReportingExportController;
use Pharma\Http\Livewire\PharmaReportingAlerts;
use Pharma\Http\Livewire\PharmaReportingDashboard;
use Pharma\Http\Livewire\PharmaReportingFinance;
use Pharma\Http\Livewire\PharmaReportingProfitability;
use Pharma\Http\Livewire\PharmaReportingPurchases;
use Pharma\Http\Livewire\PharmaReportingSales;
use Pharma\Http\Livewire\PharmaReportingStock;
use Pharma\Services\PharmaReportingService;

class PharmaReportingServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'pharma_reporting';

    public function register(): void
    {
        $this->app->singleton(PharmaReportingService::class);
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pharma');

        Livewire::component('pharma.reporting.dashboard', PharmaReportingDashboard::class);
        Livewire::component('pharma.reporting.sales', PharmaReportingSales::class);
        Livewire::component('pharma.reporting.profitability', PharmaReportingProfitability::class);
        Livewire::component('pharma.reporting.stock', PharmaReportingStock::class);
        Livewire::component('pharma.reporting.purchases', PharmaReportingPurchases::class);
        Livewire::component('pharma.reporting.finance', PharmaReportingFinance::class);
        Livewire::component('pharma.reporting.alerts', PharmaReportingAlerts::class);

        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant', 'module:pharma_reporting'])
            ->group(function () {
                Route::get('/pilotage', PharmaReportingDashboard::class)
                    ->name('tenant.pharma-reporting.dashboard');
                Route::get('/pilotage/ventes', PharmaReportingSales::class)
                    ->name('tenant.pharma-reporting.sales');
                Route::get('/pilotage/rentabilite', PharmaReportingProfitability::class)
                    ->name('tenant.pharma-reporting.profitability');
                Route::get('/pilotage/stock', PharmaReportingStock::class)
                    ->name('tenant.pharma-reporting.stock');
                Route::get('/pilotage/achats', PharmaReportingPurchases::class)
                    ->name('tenant.pharma-reporting.purchases');
                Route::get('/pilotage/finance', PharmaReportingFinance::class)
                    ->name('tenant.pharma-reporting.finance');
                Route::get('/pilotage/alertes', PharmaReportingAlerts::class)
                    ->name('tenant.pharma-reporting.alerts');
                Route::get('/pilotage/ventes/excel', [PharmaReportingExportController::class, 'excel'])
                    ->name('tenant.pharma-reporting.sales.excel');
                Route::get('/pilotage/ventes/print', [PharmaReportingExportController::class, 'print'])
                    ->name('tenant.pharma-reporting.sales.print');
                Route::get('/pilotage/rentabilite/excel', [PharmaReportingExportController::class, 'profitabilityExcel'])
                    ->name('tenant.pharma-reporting.profitability.excel');
                Route::get('/pilotage/rentabilite/print', [PharmaReportingExportController::class, 'profitabilityPrint'])
                    ->name('tenant.pharma-reporting.profitability.print');
                Route::get('/pilotage/stock/excel', [PharmaReportingExportController::class, 'stockExcel'])
                    ->name('tenant.pharma-reporting.stock.excel');
                Route::get('/pilotage/stock/print', [PharmaReportingExportController::class, 'stockPrint'])
                    ->name('tenant.pharma-reporting.stock.print');
            });
    }
}
