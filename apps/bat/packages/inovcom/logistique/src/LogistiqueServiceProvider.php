<?php

namespace InovCom\Logistique;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Logistique\Http\Livewire\DeliveriesIndex;
use InovCom\Logistique\Http\Livewire\DeliveryForm;
use InovCom\Logistique\Http\Livewire\DeliveryShow;
use InovCom\Logistique\Http\Livewire\DriversIndex;
use InovCom\Logistique\Http\Livewire\VehiclesIndex;
use InovCom\Logistique\Models\Delivery;
use InovCom\Logistique\Models\Driver;
use InovCom\Logistique\Models\Vehicle;
use Livewire\Livewire;

class LogistiqueServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'logistique';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-logistique');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-logistique-migrations');

        Livewire::component('inovcom-logistique.deliveries-index', DeliveriesIndex::class);
        Livewire::component('inovcom-logistique.delivery-form',    DeliveryForm::class);
        Livewire::component('inovcom-logistique.delivery-show',    DeliveryShow::class);
        Livewire::component('inovcom-logistique.drivers-index',    DriversIndex::class);
        Livewire::component('inovcom-logistique.vehicles-index',   VehiclesIndex::class);

        Route::bind('delivery', fn($v) => Delivery::on('tenant')->findOrFail($v));
        Route::bind('driver',   fn($v) => Driver::on('tenant')->findOrFail($v));
        Route::bind('vehicle',  fn($v) => Vehicle::on('tenant')->findOrFail($v));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/logistique', DeliveriesIndex::class)
                    ->middleware(['module:logistique'])
                    ->name('tenant.logistique.index');

                // Static sub-routes MUST come before the {delivery} wildcard
                Route::get('/logistique/create', DeliveryForm::class)
                    ->middleware(['module:logistique'])
                    ->name('tenant.logistique.create');

                Route::get('/logistique/vehicules', VehiclesIndex::class)
                    ->middleware(['module:logistique'])
                    ->name('tenant.logistique.vehicles.index');

                Route::get('/logistique/chauffeurs', DriversIndex::class)
                    ->middleware(['module:logistique'])
                    ->name('tenant.logistique.drivers.index');

                // Dynamic routes last — {delivery} would swallow any literal segment above
                Route::get('/logistique/{delivery}/edit', DeliveryForm::class)
                    ->middleware(['module:logistique'])
                    ->name('tenant.logistique.edit');

                Route::get('/logistique/{delivery}', DeliveryShow::class)
                    ->middleware(['module:logistique'])
                    ->name('tenant.logistique.show');
            });
    }
}
