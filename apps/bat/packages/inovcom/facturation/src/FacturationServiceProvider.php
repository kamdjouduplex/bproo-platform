<?php

namespace InovCom\Facturation;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Facturation\Http\Controllers\InvoicePdfController;
use InovCom\Facturation\Http\Controllers\PaymentReceiptPdfController;
use InovCom\Facturation\Http\Livewire\InvoiceForm;
use InovCom\Facturation\Http\Livewire\InvoicesIndex;
use InovCom\Facturation\Models\Invoice;
use InovCom\Facturation\Models\InvoicePayment;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class FacturationServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'facturation';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-facturation');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-facturation-migrations');

        Livewire::component('inovcom-facturation.invoices-index', InvoicesIndex::class);
        Livewire::component('inovcom-facturation.invoice-form', InvoiceForm::class);

        Route::bind('invoice', fn ($value) => Invoice::on('tenant')->findOrFail($value));
        Route::bind('payment', fn ($value) => InvoicePayment::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/facturation', InvoicesIndex::class)
                    ->middleware(['module:facturation'])
                    ->name('tenant.facturation.index');
                Route::get('/facturation/create', InvoiceForm::class)
                    ->middleware(['module:facturation'])
                    ->name('tenant.facturation.create');
                Route::get('/facturation/{invoice}/edit', InvoiceForm::class)
                    ->middleware(['module:facturation'])
                    ->name('tenant.facturation.edit');
                Route::get('/facturation/{invoice}/pdf', InvoicePdfController::class)
                    ->middleware(['module:facturation'])
                    ->name('tenant.facturation.pdf');
                Route::get('/facturation/{invoice}/paiements/{payment}/recu', PaymentReceiptPdfController::class)
                    ->middleware(['module:facturation'])
                    ->name('tenant.facturation.payment.receipt');
            });
    }
}
