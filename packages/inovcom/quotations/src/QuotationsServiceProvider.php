<?php

namespace InovCom\Quotations;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Quotations\Http\Controllers\QuotationPrintController;
use InovCom\Quotations\Http\Livewire\QuotationForm;
use InovCom\Quotations\Http\Livewire\QuotationsIndex;
use InovCom\Quotations\Models\Quotation;
use Livewire\Livewire;

class QuotationsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'quotations';

    public function register(): void
    {
        $this->app->singleton(\InovCom\Kernel\Contracts\QuotationsApi::class, \InovCom\Quotations\Services\QuotationsApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-quotations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-quotations-migrations');

        Livewire::component('inovcom-quotations.quotations-index', QuotationsIndex::class);
        Livewire::component('inovcom-quotations.quotation-form', QuotationForm::class);

        Route::bind('quotation', fn ($value) => Quotation::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/quotations', QuotationsIndex::class)
                    ->middleware(['module:quotations'])
                    ->name('tenant.quotations.index');
                Route::get('/quotations/create', QuotationForm::class)
                    ->middleware(['module:quotations'])
                    ->name('tenant.quotations.create');
                Route::get('/quotations/{quotation}/edit', QuotationForm::class)
                    ->middleware(['module:quotations'])
                    ->name('tenant.quotations.edit');
                Route::get('/quotations/{quotation}/print', QuotationPrintController::class)
                    ->middleware(['module:quotations'])
                    ->name('tenant.quotations.print');
            });
    }
}
