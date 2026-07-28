<?php

namespace InovCom\Rh;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Rh\Http\Controllers\PayslipPdfController;
use InovCom\Rh\Http\Livewire\EmployeesIndex;
use InovCom\Rh\Http\Livewire\EmployeeForm;
use InovCom\Rh\Http\Livewire\EmployeeShow;
use InovCom\Rh\Models\Employee;
use InovCom\Rh\Models\Payslip;
use Livewire\Livewire;

class RhServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'rh';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-rh');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-rh-migrations');

        Livewire::component('inovcom-rh.employees-index', EmployeesIndex::class);
        Livewire::component('inovcom-rh.employee-form',   EmployeeForm::class);
        Livewire::component('inovcom-rh.employee-show',   EmployeeShow::class);

        Route::bind('employee', fn($value) => Employee::on('tenant')->findOrFail($value));
        Route::bind('payslip', fn($value) => Payslip::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/rh', EmployeesIndex::class)
                    ->middleware(['module:rh'])
                    ->name('tenant.rh.index');

                Route::get('/rh/create', EmployeeForm::class)
                    ->middleware(['module:rh'])
                    ->name('tenant.rh.create');

                Route::get('/rh/{employee}/edit', EmployeeForm::class)
                    ->middleware(['module:rh'])
                    ->name('tenant.rh.edit');

                Route::get('/rh/{employee}', EmployeeShow::class)
                    ->middleware(['module:rh'])
                    ->name('tenant.rh.show');

                Route::get('/rh/payslips/{payslip}/pdf', PayslipPdfController::class)
                    ->middleware(['module:rh'])
                    ->name('tenant.rh.payslip.pdf');
            });
    }
}
