<?php

namespace InovCom\Clients;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Clients\Http\Livewire\ClientAccount;
use InovCom\Clients\Http\Livewire\ClientAccountView;
use InovCom\Clients\Http\Livewire\ClientForm;
use InovCom\Clients\Http\Livewire\ClientsIndex;
use InovCom\Clients\Models\Client;
use InovCom\Clients\Services\ClientsApiService;
use InovCom\Kernel\Contracts\ClientsApi;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ClientsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'clients';

    public function register(): void
    {
        $this->app->singleton(ClientsApi::class, ClientsApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-clients');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-clients-migrations');

        Livewire::component('inovcom-clients.clients-index',       ClientsIndex::class);
        Livewire::component('inovcom-clients.client-form',         ClientForm::class);
        Livewire::component('inovcom-clients.client-account',      ClientAccount::class);
        Livewire::component('inovcom-clients.client-account-view', ClientAccountView::class);

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
                Route::get('/clients/create', ClientForm::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.create');
                // Compte Client must be registered before {client} wildcard routes
                Route::get('/clients/compte', ClientAccount::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.compte');
                Route::get('/clients/{client}/compte', ClientAccountView::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.compte.view');
                Route::get('/clients/{client}/edit', ClientForm::class)
                    ->middleware(['module:clients'])
                    ->name('tenant.clients.edit');
            });
    }
}
