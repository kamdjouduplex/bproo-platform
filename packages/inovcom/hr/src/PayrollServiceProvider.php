<?php

namespace InovCom\Payroll;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Payroll\Http\Controllers\PayslipPrintController;
use InovCom\Payroll\Http\Livewire\EmployeeShow;
use InovCom\Payroll\Http\Livewire\EmployeesForm;
use InovCom\Payroll\Http\Livewire\EmployeesIndex;
use InovCom\Payroll\Http\Livewire\LeavesIndex;
use InovCom\Payroll\Http\Livewire\PayrollRunForm;
use InovCom\Payroll\Http\Livewire\PayrollRunsIndex;
use InovCom\Payroll\Models\Employee as EmployeeModel;
use InovCom\Payroll\Models\PayrollRun as PayrollRunModel;
use InovCom\Payroll\Services\EmployeeService;
use InovCom\Payroll\Services\LeaveService;
use InovCom\Payroll\Services\PayrollAdjustmentService;
use InovCom\Payroll\Services\PayrollCalculationService;
use InovCom\Payroll\Services\PayrollService;
use InovCom\Payroll\Services\UserEmployeeSyncService;
use Livewire\Livewire;

class PayrollServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'payroll';

    public function register(): void
    {
        $this->app->singleton(EmployeeService::class);
        $this->app->singleton(LeaveService::class);
        $this->app->singleton(PayrollAdjustmentService::class);
        $this->app->singleton(PayrollCalculationService::class);
        $this->app->singleton(PayrollService::class);
        $this->app->singleton(UserEmployeeSyncService::class);
        $this->app->singleton(\InovCom\Kernel\Contracts\PayrollApi::class, \InovCom\Payroll\Services\PayrollApiService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-payroll');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-payroll-migrations');

        Livewire::component('inovcom-payroll.employees-index', EmployeesIndex::class);
        Livewire::component('inovcom-payroll.employees-form', EmployeesForm::class);
        Livewire::component('inovcom-payroll.employee-show', EmployeeShow::class);
        Livewire::component('inovcom-payroll.leaves-index', LeavesIndex::class);
        Livewire::component('inovcom-payroll.payroll-runs-index', PayrollRunsIndex::class);
        Livewire::component('inovcom-payroll.payroll-run-form', PayrollRunForm::class);

        Route::bind('employee', function ($value) {
            return EmployeeModel::on('tenant')->findOrFail($value);
        });
        Route::bind('payroll_run', function ($value) {
            return PayrollRunModel::on('tenant')->findOrFail($value);
        });

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/payroll', PayrollRunsIndex::class)
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.index');
                Route::get('/payroll/create', PayrollRunForm::class)
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.create');
                Route::get('/payroll/leaves', LeavesIndex::class)
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.leaves.index');
                // Employés : création/gestion via Utilisateurs — anciennes URLs redirigées.
                Route::get('/payroll/employees/list', fn () => redirect()->route('tenant.users.index', request()->query()))
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.employees.index');
                Route::get('/payroll/employees/create', fn () => redirect()->route('tenant.users.create', request()->query()))
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.employees.create');
                Route::get('/payroll/employees/{employee}', EmployeeShow::class)
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.employees.show');
                Route::get('/payroll/employees/{employee}/edit', function ($employee) {
                    $emp = EmployeeModel::on('tenant')->findOrFail($employee);
                    if ($emp->user_id && \Illuminate\Support\Facades\Route::has('tenant.users.edit')) {
                        return redirect()->route('tenant.users.edit', array_merge(
                            ['user' => $emp->user_id],
                            request()->query()
                        ));
                    }

                    return redirect()->route('tenant.users.index', request()->query());
                })
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.employees.edit');
                Route::get('/payroll/{payroll_run}/payslip/{line}', PayslipPrintController::class)
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.payslip.print');
                Route::get('/payroll/{payroll_run}', PayrollRunForm::class)
                    ->middleware(['module:payroll'])
                    ->name('tenant.payroll.show');
            });
    }
}
