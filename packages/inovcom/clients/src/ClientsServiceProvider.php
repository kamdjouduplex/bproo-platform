<?php

namespace InovCom\Clients;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Clients\Http\Livewire\Client360Show;
use InovCom\Clients\Http\Livewire\ClientShow;
use InovCom\Clients\Http\Livewire\ClientsDuplicates;
use InovCom\Clients\Http\Livewire\ClientsForm;
use InovCom\Clients\Http\Livewire\ClientsIndex;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Services\ClientsApiService;
use InovCom\Kernel\Contracts\ClientsApi;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ClientsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'clients';

    public function register(): void
    {
        // Register ClientsApi interface implementation
        $this->app->singleton(ClientsApi::class, ClientsApiService::class);
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-clients');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-clients-migrations');

        Livewire::component('inovcom-clients.clients-index', ClientsIndex::class);
        Livewire::component('inovcom-clients.clients-form', ClientsForm::class);
        Livewire::component('inovcom-clients.client-show', ClientShow::class);
        Livewire::component('inovcom-clients.client-360-show', Client360Show::class);
        Livewire::component('inovcom-clients.clients-duplicates', ClientsDuplicates::class);

        Route::bind('client', fn ($value) => Client::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/clients', ClientsIndex::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.index');
                Route::get('/clients/create', ClientsForm::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.create');
                Route::get('/clients/duplicates', ClientsDuplicates::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.duplicates');
                Route::get('/clients/{client}/edit', ClientsForm::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.edit');
                Route::get('/clients/{client}/360', Client360Show::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.show360');
                Route::get('/clients/{client}', ClientShow::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.show');
            });
    }
}
