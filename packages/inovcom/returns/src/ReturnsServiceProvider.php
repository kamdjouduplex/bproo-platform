<?php

namespace InovCom\Returns;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Returns\Http\Livewire\CreditNotesIndex;
use InovCom\Returns\Http\Livewire\CreditNoteShow;
use InovCom\Returns\Http\Livewire\CustomerCreditLedger;
use InovCom\Returns\Http\Livewire\RefundsIndex;
use InovCom\Returns\Http\Livewire\ReturnCreate;
use InovCom\Returns\Http\Livewire\ReturnsIndex;
use InovCom\Returns\Http\Livewire\ReturnShow;
use InovCom\Returns\Services\AuditLogger;
use InovCom\Returns\Services\CreditNoteService;
use InovCom\Returns\Services\CustomerCreditService;
use InovCom\Returns\Services\RefundService;
use InovCom\Returns\Services\ReturnNumberGenerator;
use InovCom\Returns\Services\ReturnService;
use InovCom\Returns\Services\ReturnStockService;
use Livewire\Livewire;

class ReturnsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'returns';

    public function register(): void
    {
        $this->app->singleton(ReturnNumberGenerator::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ReturnStockService::class);
        $this->app->singleton(CustomerCreditService::class);
        $this->app->singleton(ReturnService::class);
        $this->app->singleton(CreditNoteService::class);
        $this->app->singleton(RefundService::class);
    }

    public function boot(): void
    {
        if (! $this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-returns');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-returns-migrations');

        Livewire::component('inovcom-returns.returns-index', ReturnsIndex::class);
        Livewire::component('inovcom-returns.return-create', ReturnCreate::class);
        Livewire::component('inovcom-returns.return-show', ReturnShow::class);
        Livewire::component('inovcom-returns.credit-notes-index', CreditNotesIndex::class);
        Livewire::component('inovcom-returns.credit-note-show', CreditNoteShow::class);
        Livewire::component('inovcom-returns.refunds-index', RefundsIndex::class);
        Livewire::component('inovcom-returns.customer-credit-ledger', CustomerCreditLedger::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/returns', ReturnsIndex::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.index');

                Route::get('/returns/create', ReturnCreate::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.create');

                Route::get('/returns/from-invoice/{invoice}', ReturnCreate::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.create_from_invoice');

                Route::get('/returns/credit-notes', CreditNotesIndex::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.credit_notes.index');

                Route::get('/returns/credit-notes/{creditNote}', CreditNoteShow::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.credit_notes.show');

                Route::get('/returns/refunds', RefundsIndex::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.refunds.index');

                Route::get('/returns/customer-credits', CustomerCreditLedger::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.customer_credits.index');

                Route::get('/returns/{return}', ReturnShow::class)
                    ->middleware(['module:returns'])
                    ->name('tenant.returns.show');
            });
    }
}
