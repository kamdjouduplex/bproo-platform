<?php

namespace InovCom\Reporting;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Reporting\Http\Controllers\ReportingExplorerExportController;
use InovCom\Reporting\Http\Livewire\ReportingIndex;
use Livewire\Livewire;

class ReportingServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'reporting';

    public function register(): void
    {
        $this->app->singleton(Services\ReportingService::class);
        $this->app->singleton(Services\ReportRunner::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-reporting');

        Livewire::component('inovcom-reporting.reporting-index', ReportingIndex::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/reporting', ReportingIndex::class)
                    ->middleware(['module:reporting'])
                    ->name('tenant.reporting.index');
                Route::get('/reporting/explorer/pdf', [ReportingExplorerExportController::class, 'pdf'])
                    ->middleware(['module:reporting'])
                    ->name('tenant.reporting.explorer.pdf');
                Route::get('/reporting/explorer/print', [ReportingExplorerExportController::class, 'print'])
                    ->middleware(['module:reporting'])
                    ->name('tenant.reporting.explorer.print');
            });
    }
}
