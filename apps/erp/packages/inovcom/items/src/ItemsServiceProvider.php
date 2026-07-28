<?php

namespace InovCom\Items;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Items\Http\Livewire\ItemsForm;
use InovCom\Items\Http\Livewire\ItemsIndex;
use InovCom\Items\Http\Livewire\ItemsListConfig;
use InovCom\Items\Http\Livewire\ItemsShow;
use InovCom\Items\Models\Item;
use InovCom\Items\Services\ItemsApiService;
use InovCom\Kernel\Contracts\ItemsApi;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ItemsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'items';

    public function register(): void
    {
        // Register ItemsApi interface implementation
        $this->app->singleton(ItemsApi::class, ItemsApiService::class);
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-items');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-items-migrations');

        Livewire::component('inovcom-items.items-index', ItemsIndex::class);
        Livewire::component('inovcom-items.items-form', ItemsForm::class);
        Livewire::component('inovcom-items.items-show', ItemsShow::class);
        Livewire::component('inovcom-items.items-list-config', ItemsListConfig::class);

        Route::bind('item', fn ($value) => Item::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/items', ItemsIndex::class)
                    ->middleware(['module:items'])
                    ->name('tenant.items.index');
                Route::get('/items/list-config', ItemsListConfig::class)
                    ->middleware(['module:items'])
                    ->name('tenant.items.list-config');
                Route::get('/items/create', ItemsForm::class)
                    ->middleware(['module:items'])
                    ->name('tenant.items.create');
                Route::get('/items/{item}', ItemsShow::class)
                    ->middleware(['module:items'])
                    ->name('tenant.items.show');
                Route::get('/items/{item}/edit', ItemsForm::class)
                    ->middleware(['module:items'])
                    ->name('tenant.items.edit');
            });
    }
}
