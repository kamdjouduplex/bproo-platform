<?php

namespace InovCom\Reservations;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Reservations\Http\Livewire\ReservationForm;
use InovCom\Reservations\Http\Livewire\ReservationShow;
use InovCom\Reservations\Http\Livewire\ReservationsIndex;
use InovCom\Reservations\Models\Reservation;
use Livewire\Livewire;

class ReservationsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'reservations';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-reservations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-reservations-migrations');

        Livewire::component('inovcom-reservations.reservations-index', ReservationsIndex::class);
        Livewire::component('inovcom-reservations.reservation-form', ReservationForm::class);
        Livewire::component('inovcom-reservations.reservation-show', ReservationShow::class);

        Route::bind('reservation', fn ($value) => Reservation::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/reservations', ReservationsIndex::class)
                    ->middleware(['module:reservations'])
                    ->name('tenant.reservations.index');
                Route::get('/reservations/create', ReservationForm::class)
                    ->middleware(['module:reservations'])
                    ->name('tenant.reservations.create');
                Route::get('/reservations/{reservation}', ReservationShow::class)
                    ->middleware(['module:reservations'])
                    ->name('tenant.reservations.show');
            });
    }
}
