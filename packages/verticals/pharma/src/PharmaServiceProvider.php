<?php

namespace Pharma;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;
use Pharma\Http\Livewire\PharmaHub;

class PharmaServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'pharma';

    public function register(): void
    {
        // Always register publish tags (provisioning / artisan must work without tenant context).
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'bproo-pharma-migrations');
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pharma');

        Livewire::component('pharma.hub', PharmaHub::class);

        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant', 'module:pharma'])
            ->group(function () {
                Route::get('/pharma', PharmaHub::class)->name('tenant.pharma.hub');
            });
    }
}
