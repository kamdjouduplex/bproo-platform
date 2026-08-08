<?php

namespace InovCom\Prescriptions;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Prescriptions\Http\Controllers\PrescriptionPrintController;
use InovCom\Prescriptions\Http\Livewire\PrescriptionForm;
use InovCom\Prescriptions\Http\Livewire\PrescriptionsIndex;
use InovCom\Prescriptions\Models\Prescription;
use InovCom\Prescriptions\Services\PrescriptionsApiService;
use InovCom\Kernel\Contracts\PrescriptionsApi;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class PrescriptionsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'prescriptions';

    public function register(): void
    {
        // Bound even when module UI is off — Sales detects via isAvailable().
        $this->app->singleton(PrescriptionsApi::class, PrescriptionsApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-prescriptions');
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-prescriptions-migrations');

        Livewire::component('inovcom-prescriptions.prescriptions-index', PrescriptionsIndex::class);
        Livewire::component('inovcom-prescriptions.prescription-form', PrescriptionForm::class);
        Route::bind('prescription', fn ($value) => Prescription::on('tenant')->findOrFail($value));
        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/prescriptions', PrescriptionsIndex::class)
                    ->middleware(['module:prescriptions'])
                    ->name('tenant.prescriptions.index');
                Route::get('/prescriptions/create', PrescriptionForm::class)
                    ->middleware(['module:prescriptions'])
                    ->name('tenant.prescriptions.create');
                Route::get('/prescriptions/{prescription}/edit', PrescriptionForm::class)
                    ->middleware(['module:prescriptions'])
                    ->name('tenant.prescriptions.edit');
                Route::get('/prescriptions/{prescription}/print', PrescriptionPrintController::class)
                    ->middleware(['module:prescriptions'])
                    ->name('tenant.prescriptions.print');
            });
    }
}
