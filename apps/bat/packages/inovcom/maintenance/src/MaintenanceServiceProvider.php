<?php

namespace InovCom\Maintenance;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Maintenance\Http\Livewire\ContractsIndex;
use InovCom\Maintenance\Http\Livewire\ContractForm;
use InovCom\Maintenance\Http\Livewire\OrdersIndex;
use InovCom\Maintenance\Http\Livewire\OrderForm;
use InovCom\Maintenance\Http\Livewire\InterventionForm;
use InovCom\Maintenance\Models\MaintenanceContract;
use InovCom\Maintenance\Models\MaintenanceOrder;
use Livewire\Livewire;

class MaintenanceServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'maintenance';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-maintenance');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-maintenance-migrations');

        Livewire::component('inovcom-maintenance.contracts-index', ContractsIndex::class);
        Livewire::component('inovcom-maintenance.contract-form', ContractForm::class);
        Livewire::component('inovcom-maintenance.orders-index', OrdersIndex::class);
        Livewire::component('inovcom-maintenance.order-form', OrderForm::class);
        Livewire::component('inovcom-maintenance.intervention-form', InterventionForm::class);

        Route::bind('maintenance_contract', fn ($value) => MaintenanceContract::on('tenant')->findOrFail($value));
        Route::bind('maintenance_order', fn ($value) => MaintenanceOrder::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                // Contracts
                Route::get('/maintenance/contracts', ContractsIndex::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.contracts.index');
                Route::get('/maintenance/contracts/create', ContractForm::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.contracts.create');
                Route::get('/maintenance/contracts/{maintenance_contract}/edit', ContractForm::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.contracts.edit');

                // Orders
                Route::get('/maintenance/orders', OrdersIndex::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.orders.index');
                Route::get('/maintenance/orders/create', OrderForm::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.orders.create');
                Route::get('/maintenance/orders/{maintenance_order}/edit', OrderForm::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.orders.edit');

                // Interventions (always accessed via an order)
                Route::get('/maintenance/orders/{maintenance_order}/interventions/create', InterventionForm::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.interventions.create');
                Route::get('/maintenance/interventions/{intervention}/edit', InterventionForm::class)
                    ->middleware(['module:maintenance'])
                    ->name('tenant.maintenance.interventions.edit');
            });
    }
}
