<?php

namespace InovCom\Losses;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Losses\Http\Livewire\LossesForm;
use InovCom\Losses\Http\Livewire\LossesIndex;
use InovCom\Losses\Models\LossRecord;
use Livewire\Livewire;

class LossesServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'losses';

    public function register(): void
    {
        $this->app->singleton(\InovCom\Kernel\Contracts\LossesApi::class, \InovCom\Losses\Services\LossesApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-losses');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-losses-migrations');

        Livewire::component('inovcom-losses.losses-index', LossesIndex::class);
        Livewire::component('inovcom-losses.losses-form', LossesForm::class);

        Route::bind('loss_record', fn ($value) => LossRecord::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/losses', LossesIndex::class)
                    ->middleware(['module:losses'])
                    ->name('tenant.losses.index');
                Route::get('/losses/create', LossesForm::class)
                    ->middleware(['module:losses'])
                    ->name('tenant.losses.create');
                Route::get('/losses/{loss_record}/edit', LossesForm::class)
                    ->middleware(['module:losses'])
                    ->name('tenant.losses.edit');
            });
    }
}
