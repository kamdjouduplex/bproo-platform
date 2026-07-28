<?php

namespace InovCom\Configuration;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Configuration\Http\Livewire\ConfigurationIndex;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ConfigurationServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'configuration';

    public function register(): void
    {
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-configuration');

        Livewire::component('inovcom-configuration.configuration-index', ConfigurationIndex::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/configuration', ConfigurationIndex::class)
                    ->middleware(['module:configuration'])
                    ->name('tenant.configuration.index');
            });
    }
}
