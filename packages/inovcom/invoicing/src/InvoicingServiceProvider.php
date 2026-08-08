<?php

namespace InovCom\Invoicing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Invoicing\Http\Controllers\CollectionReminderPdfController;
use InovCom\Invoicing\Http\Controllers\CollectionReminderPrintController;
use InovCom\Invoicing\Http\Controllers\DeliveryNotePrintController;
use InovCom\Invoicing\Http\Controllers\InvoicePrintController;
use InovCom\Invoicing\Http\Livewire\CollectionRemindersIndex;
use InovCom\Invoicing\Http\Livewire\DeliveryNoteForm;
use InovCom\Invoicing\Http\Livewire\DeliveryNotesIndex;
use InovCom\Invoicing\Http\Livewire\DeliveryNoteShow;
use InovCom\Invoicing\Http\Livewire\InvoiceForm;
use InovCom\Invoicing\Models\DeliveryNote;
use InovCom\Invoicing\Services\CollectionReminderService;
use InovCom\Invoicing\Services\DeliveryNotesService;
use InovCom\Invoicing\Http\Livewire\InvoicesIndex;
use InovCom\Invoicing\Models\Invoice;
use InovCom\Invoicing\Services\InvoicingApiService;
use InovCom\Kernel\Contracts\InvoicingApi;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class InvoicingServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'invoicing';

    public function register(): void
    {
        $this->app->singleton(DeliveryNotesService::class);
        $this->app->singleton(CollectionReminderService::class);
        $this->app->singleton(\InovCom\Invoicing\Services\InvoiceScheduleService::class);
        $this->app->singleton(InvoicingApi::class, InvoicingApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-invoicing');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-invoicing-migrations');

        Livewire::component('inovcom-invoicing.invoices-index', InvoicesIndex::class);
        Livewire::component('inovcom-invoicing.invoice-form', InvoiceForm::class);
        Livewire::component('inovcom-invoicing.delivery-notes-index', DeliveryNotesIndex::class);
        Livewire::component('inovcom-invoicing.delivery-note-form', DeliveryNoteForm::class);
        Livewire::component('inovcom-invoicing.delivery-note-show', DeliveryNoteShow::class);
        Livewire::component('inovcom-invoicing.collection-reminders-index', CollectionRemindersIndex::class);

        Route::bind('invoice', fn ($value) => Invoice::on('tenant')->findOrFail($value));
        Route::bind('deliveryNote', fn ($value) => DeliveryNote::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/invoices', InvoicesIndex::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.index');
                Route::get('/invoices/collection-reminders', CollectionRemindersIndex::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.collection_reminders.index');
                Route::get('/invoices/collection-reminders/print', CollectionReminderPrintController::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.collection_reminders.print');
                Route::get('/invoices/collection-reminders/pdf', CollectionReminderPdfController::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.collection_reminders.pdf');
                Route::get('/invoices/create', InvoiceForm::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.create');
                Route::get('/invoices/deliveries', DeliveryNotesIndex::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.deliveries.index');
                Route::get('/invoices/deliveries/new', DeliveryNoteForm::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.deliveries.from_quotation');
                Route::get('/invoices/deliveries/{deliveryNote}', DeliveryNoteShow::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.deliveries.show');
                Route::get('/invoices/deliveries/{deliveryNote}/edit', DeliveryNoteForm::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.deliveries.edit');
                Route::get('/invoices/deliveries/{deliveryNote}/print', DeliveryNotePrintController::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.deliveries.print');
                Route::get('/invoices/{invoice}/deliveries/create', DeliveryNoteForm::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.deliveries.create');
                Route::get('/invoices/{invoice}/edit', InvoiceForm::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.edit');
                Route::get('/invoices/{invoice}/print', InvoicePrintController::class)
                    ->middleware(['module:invoicing'])
                    ->name('tenant.invoicing.print');
            });
    }
}
