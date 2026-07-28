<?php

namespace InovCom\Debts;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Debts\Http\Livewire\DebtForm;
use InovCom\Debts\Http\Livewire\DebtsIndex;
use InovCom\Debts\Http\Livewire\PaymentForm;
use InovCom\Debts\Models\Debt;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class DebtsServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'debts';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-debts');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-debts-migrations');

        Livewire::component('inovcom-debts.debts-index', DebtsIndex::class);
        Livewire::component('inovcom-debts.debt-form', DebtForm::class);
        Livewire::component('inovcom-debts.payment-form', PaymentForm::class);

        Route::bind('debt', fn ($value) => Debt::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/debts', DebtsIndex::class)
                    ->middleware(['module:debts'])
                    ->name('tenant.debts.index');
                Route::get('/debts/create', DebtForm::class)
                    ->middleware(['module:debts'])
                    ->name('tenant.debts.create');
                Route::get('/debts/{debt}/edit', DebtForm::class)
                    ->middleware(['module:debts'])
                    ->name('tenant.debts.edit');
                Route::get('/debts/{debt}/pay', PaymentForm::class)
                    ->middleware(['module:debts'])
                    ->name('tenant.debts.pay');
            });
    }
}
