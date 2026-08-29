<?php

namespace InovCom\Treasury;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Treasury\Http\Livewire\TreasuryCommitmentForm;
use InovCom\Treasury\Http\Livewire\TreasuryDashboard;
use InovCom\Treasury\Http\Livewire\TreasurySettingsForm;
use InovCom\Treasury\Models\TreasuryCommitment;
use Livewire\Livewire;

class TreasuryServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'treasury';

    public function register(): void
    {
        $this->app->singleton(\InovCom\Treasury\Services\TreasurySettings::class);
        $this->app->singleton(\InovCom\Treasury\Services\TreasuryForecastService::class);
        $this->app->singleton(\InovCom\Treasury\Services\TreasuryService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-treasury-migrations');

        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-treasury');

        Livewire::component('inovcom-treasury.dashboard', TreasuryDashboard::class);
        Livewire::component('inovcom-treasury.form', TreasuryCommitmentForm::class);
        Livewire::component('inovcom-treasury.settings', TreasurySettingsForm::class);

        Route::bind('commitment', fn ($value) => TreasuryCommitment::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/treasury', TreasuryDashboard::class)
                    ->middleware(['module:treasury'])
                    ->name('tenant.treasury.index');
                Route::get('/treasury/settings', TreasurySettingsForm::class)
                    ->middleware(['module:treasury'])
                    ->name('tenant.treasury.settings');
                Route::get('/treasury/create', TreasuryCommitmentForm::class)
                    ->middleware(['module:treasury'])
                    ->name('tenant.treasury.create');
                Route::get('/treasury/{commitment}/edit', TreasuryCommitmentForm::class)
                    ->middleware(['module:treasury'])
                    ->name('tenant.treasury.edit');
            });
    }
}
