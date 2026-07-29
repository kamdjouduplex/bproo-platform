<?php

namespace InovCom\Caisse;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Caisse\Http\Livewire\CaisseIndex;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class CaisseServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'caisse';

    public function register(): void
    {
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-caisse');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-caisse-migrations');

        Livewire::component('inovcom-caisse.caisse-index', CaisseIndex::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/caisse', CaisseIndex::class)
                    ->middleware(['module:caisse'])
                    ->name('tenant.caisse.index');
            });
    }
}
