<?php

namespace InovCom\InvoicePayments;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\InvoicePayments\Http\Controllers\InvoicePaymentPrintController;
use InovCom\InvoicePayments\Http\Livewire\InvoicePaymentForm;
use InovCom\InvoicePayments\Http\Livewire\InvoicePaymentsIndex;
use InovCom\InvoicePayments\Models\InvoicePayment;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class InvoicePaymentsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'invoice_payments';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-invoice-payments');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-invoice-payments-migrations');

        Livewire::component('inovcom-invoice-payments.index', InvoicePaymentsIndex::class);
        Livewire::component('inovcom-invoice-payments.payment-form', InvoicePaymentForm::class);

        Route::bind('invoice', fn ($value) => Invoice::on('tenant')->findOrFail($value));
        Route::bind('invoicePayment', fn ($value) => InvoicePayment::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/invoice-payments', InvoicePaymentsIndex::class)
                    ->middleware(['module:invoice_payments'])
                    ->name('tenant.invoice_payments.index');
                Route::get('/invoice-payments/{invoice}/pay', InvoicePaymentForm::class)
                    ->middleware(['module:invoice_payments'])
                    ->name('tenant.invoice_payments.pay');
                Route::get('/invoice-payments/receipts/{invoicePayment}/print', InvoicePaymentPrintController::class)
                    ->middleware(['module:invoice_payments'])
                    ->name('tenant.invoice_payments.receipt.print');
            });
    }
}
