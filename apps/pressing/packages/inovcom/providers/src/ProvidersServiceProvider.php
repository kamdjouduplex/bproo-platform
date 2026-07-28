<?php

namespace InovCom\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Providers\Http\Livewire\ProvidersForm;
use InovCom\Providers\Http\Livewire\ProvidersIndex;
use InovCom\Providers\Http\Livewire\ProvidersShow;
use InovCom\Providers\Models\Provider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ProvidersServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'providers';

    public function register(): void
    {
        // Register ProvidersApiService if needed
        // $this->app->singleton(ProvidersApi::class, ProvidersApiService::class);
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-providers');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-providers-migrations');

        Livewire::component('inovcom-providers.providers-index', ProvidersIndex::class);
        Livewire::component('inovcom-providers.providers-form', ProvidersForm::class);
        Livewire::component('inovcom-providers.providers-show', ProvidersShow::class);

        Route::bind('provider', fn ($value) => Provider::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/providers', ProvidersIndex::class)
                    ->middleware(['module:providers'])
                    ->name('tenant.providers.index');
                Route::get('/providers/create', ProvidersForm::class)
                    ->middleware(['module:providers'])
                    ->name('tenant.providers.create');
                Route::get('/providers/{provider}', ProvidersShow::class)
                    ->middleware(['module:providers'])
                    ->name('tenant.providers.show');
                Route::get('/providers/{provider}/edit', ProvidersForm::class)
                    ->middleware(['module:providers'])
                    ->name('tenant.providers.edit');
            });
    }
}
