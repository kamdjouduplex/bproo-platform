<?php

namespace InovCom\Offres;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Offres\Http\Livewire\OfferForm;
use InovCom\Offres\Http\Livewire\OffersIndex;
use InovCom\Offres\Http\Livewire\OffersKanban;
use InovCom\Offres\Models\Offer;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class OffresServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'offres';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-offres');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-offres-migrations');

        Livewire::component('inovcom-offres.offers-index', OffersIndex::class);
        Livewire::component('inovcom-offres.offer-form', OfferForm::class);
        Livewire::component('inovcom-offres.offers-kanban', OffersKanban::class);

        Route::bind('offer', fn ($value) => Offer::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/offres', OffersIndex::class)
                    ->middleware(['module:offres'])
                    ->name('tenant.offres.index');
                Route::get('/offres/create', OfferForm::class)
                    ->middleware(['module:offres'])
                    ->name('tenant.offres.create');
                Route::get('/offres/{offer}/edit', OfferForm::class)
                    ->middleware(['module:offres'])
                    ->name('tenant.offres.edit');
                Route::get('/offres/kanban', OffersKanban::class)
                    ->middleware(['module:offres'])
                    ->name('tenant.offres.kanban');
            });
    }
}
