<?php

namespace InovCom\Attendance;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Attendance\Http\Controllers\AttendanceSheetPrintController;
use InovCom\Attendance\Http\Controllers\AttendanceTeamSheetPrintController;
use InovCom\Attendance\Http\Livewire\AttendanceIndex;
use InovCom\Attendance\Http\Livewire\AttendancePunchWidget;
use InovCom\Attendance\Http\Livewire\AttendanceSheet;
use InovCom\Attendance\Services\AttendanceService;
use InovCom\Kernel\Traits\LazyModuleBoot;
use Livewire\Livewire;

class AttendanceServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'attendance';

    public function register(): void
    {
        $this->app->singleton(AttendanceService::class);
    }

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-attendance');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant_modules'),
        ], 'inovcom-attendance-migrations');

        Livewire::component('inovcom-attendance.punch-widget', AttendancePunchWidget::class);
        Livewire::component('inovcom-attendance.index', AttendanceIndex::class);
        Livewire::component('inovcom-attendance.sheet', AttendanceSheet::class);

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/attendance', AttendanceIndex::class)
                    ->middleware(['module:attendance'])
                    ->name('tenant.attendance.index');
                Route::get('/attendance/sheet', AttendanceSheet::class)
                    ->middleware(['module:attendance'])
                    ->name('tenant.attendance.sheet');
                Route::get('/attendance/sheet/print', AttendanceSheetPrintController::class)
                    ->middleware(['module:attendance'])
                    ->name('tenant.attendance.sheet.print');
                Route::get('/attendance/sheet/print-team', AttendanceTeamSheetPrintController::class)
                    ->middleware(['module:attendance'])
                    ->name('tenant.attendance.sheet.print-team');
            });
    }
}
