<?php

namespace InovCom\Crm;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Crm\Http\Livewire\CrmActivities;
use InovCom\Crm\Http\Livewire\CrmKpis;
use InovCom\Crm\Http\Livewire\CrmOpportunities;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class CrmServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'crm';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'inovcom-crm');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-crm-suite-migrations');

        Livewire::component('inovcom-crm.kpis', CrmKpis::class);
        Livewire::component('inovcom-crm.opportunities', CrmOpportunities::class);
        Livewire::component('inovcom-crm.activities', CrmActivities::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/crm', fn () => redirect()->route('tenant.crm.kpi'))
                    ->middleware(['module:crm'])
                    ->name('tenant.crm.index');
                Route::get('/crm/kpi', CrmKpis::class)
                    ->middleware(['module:crm'])
                    ->name('tenant.crm.kpi');
                Route::get('/crm/opportunities', CrmOpportunities::class)
                    ->middleware(['module:crm'])
                    ->name('tenant.crm.opportunities');
                Route::get('/crm/activities', CrmActivities::class)
                    ->middleware(['module:crm'])
                    ->name('tenant.crm.activities');
            });
    }
}
