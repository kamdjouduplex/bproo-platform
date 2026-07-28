<?php

namespace InovCom\Batches;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Batches\Http\Livewire\BatchForm;
use InovCom\Batches\Http\Livewire\BatchesIndex;
use InovCom\Batches\Services\BatchesApiService;
use InovCom\Kernel\Contracts\BatchesApi;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class BatchesServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'batches';

    public function register(): void
    {
        $this->app->singleton(BatchesApi::class, BatchesApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-batches');
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-batches-migrations');

        Livewire::component('inovcom-batches.batches-index', BatchesIndex::class);
        Livewire::component('inovcom-batches.batch-form', BatchForm::class);
        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/batches', BatchesIndex::class)
                    ->middleware(['module:batches'])
                    ->name('tenant.batches.index');
                Route::get('/batches/create', BatchForm::class)
                    ->middleware(['module:batches'])
                    ->name('tenant.batches.create');
            });
    }
}
