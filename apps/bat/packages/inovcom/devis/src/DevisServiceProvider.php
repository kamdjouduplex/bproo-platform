<?php

namespace InovCom\Devis;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Devis\Http\Controllers\QuoteEmailDraftController;
use InovCom\Devis\Http\Controllers\QuoteExcelController;
use InovCom\Devis\Http\Controllers\QuotePdfController;
use InovCom\Devis\Http\Livewire\QuoteForm;
use InovCom\Devis\Http\Livewire\QuoteShow;
use InovCom\Devis\Http\Livewire\QuotesIndex;
use InovCom\Devis\Models\Quote;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class DevisServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'devis';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-devis');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-devis-migrations');

        Livewire::component('inovcom-devis.quotes-index', QuotesIndex::class);
        Livewire::component('inovcom-devis.quote-form', QuoteForm::class);
        Livewire::component('inovcom-devis.quote-show', QuoteShow::class);

        Route::bind('quote', fn ($value) => Quote::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/devis', QuotesIndex::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.index');
                Route::get('/devis/create', QuoteForm::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.create');
                Route::get('/devis/{quote}/edit', QuoteForm::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.edit');
                Route::get('/devis/{quote}/pdf', QuotePdfController::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.pdf');
                Route::get('/devis/{quote}/excel', QuoteExcelController::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.excel');
                Route::get('/devis/{quote}/email-draft', QuoteEmailDraftController::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.email');
                Route::get('/devis/{quote}', QuoteShow::class)
                    ->middleware(['module:devis'])
                    ->name('tenant.devis.show');
            });
    }
}
