<?php

namespace InovCom\Branding;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Branding\Http\Livewire\BrandingIndex;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class BrandingServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     * Branding is a core module, so it will always boot, but we check for consistency
     */
    protected string $moduleKey = 'branding';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        // Core modules always boot, but we check for consistency
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-branding');

        Livewire::component('inovcom-branding.branding-index', BrandingIndex::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/branding', BrandingIndex::class)
                    ->middleware(['module:branding'])
                    ->name('tenant.branding.index');
            });
    }
}
