<?php

namespace InovCom\Expenses;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Expenses\Http\Livewire\ExpensesForm;
use InovCom\Expenses\Http\Livewire\ExpensesIndex;
use InovCom\Expenses\Models\Expense;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class ExpensesServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    /**
     * Module key for lazy loading
     */
    protected string $moduleKey = 'expenses';

    public function register(): void
    {
        // Register services if needed
    }

    public function boot(): void
    {
        // Only boot if module is enabled for current tenant
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-expenses');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-expenses-migrations');

        Livewire::component('inovcom-expenses.expenses-index', ExpensesIndex::class);
        Livewire::component('inovcom-expenses.expenses-form', ExpensesForm::class);

        Route::bind('expense', fn ($value) => Expense::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/expenses', ExpensesIndex::class)
                    ->middleware(['module:expenses'])
                    ->name('tenant.expenses.index');
                Route::get('/expenses/create', ExpensesForm::class)
                    ->middleware(['module:expenses'])
                    ->name('tenant.expenses.create');
                Route::get('/expenses/{expense}/edit', ExpensesForm::class)
                    ->middleware(['module:expenses'])
                    ->name('tenant.expenses.edit');
            });
    }
}
