<?php

namespace InovCom\Planning;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Planning\Http\Livewire\PlanningCalendar;
use InovCom\Planning\Http\Livewire\AppointmentForm;
use InovCom\Planning\Models\Appointment;
use Livewire\Livewire;

class PlanningServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'planning';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-planning');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-planning-migrations');

        Livewire::component('inovcom-planning.calendar', PlanningCalendar::class);
        Livewire::component('inovcom-planning.appointment-form', AppointmentForm::class);

        Route::bind('appointment', fn($value) => Appointment::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/planning', PlanningCalendar::class)
                    ->middleware(['module:planning'])
                    ->name('tenant.planning.index');

                Route::get('/planning/create', AppointmentForm::class)
                    ->middleware(['module:planning'])
                    ->name('tenant.planning.create');

                Route::get('/planning/{appointment}/edit', AppointmentForm::class)
                    ->middleware(['module:planning'])
                    ->name('tenant.planning.edit');
            });
    }
}
