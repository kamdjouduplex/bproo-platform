<?php

namespace InovCom\Tickets;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Tickets\Http\Livewire\TicketForm;
use InovCom\Tickets\Http\Livewire\TicketShow;
use InovCom\Tickets\Http\Livewire\TicketsIndex;
use InovCom\Tickets\Models\Ticket;
use Livewire\Livewire;

class TicketsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'tickets';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-tickets');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-tickets-migrations');

        Livewire::component('inovcom-tickets.tickets-index', TicketsIndex::class);
        Livewire::component('inovcom-tickets.ticket-form', TicketForm::class);
        Livewire::component('inovcom-tickets.ticket-show', TicketShow::class);

        Route::bind('ticket', fn ($value) => Ticket::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/tickets', TicketsIndex::class)
                    ->middleware(['module:tickets'])
                    ->name('tenant.tickets.index');
                Route::get('/tickets/create', TicketForm::class)
                    ->middleware(['module:tickets'])
                    ->name('tenant.tickets.create');
                Route::get('/tickets/{ticket}', TicketShow::class)
                    ->middleware(['module:tickets'])
                    ->name('tenant.tickets.show');
            });
    }
}
