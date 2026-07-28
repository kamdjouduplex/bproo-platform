<?php

namespace InovCom\Suivi;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InovCom\Kernel\Traits\LazyModuleBoot;
use InovCom\Suivi\Http\Livewire\SiteReportsIndex;
use InovCom\Suivi\Http\Livewire\SiteReportForm;
use InovCom\Suivi\Http\Livewire\TaskBoard;
use InovCom\Suivi\Models\SiteReport;
use Livewire\Livewire;

class SuiviServiceProvider extends ServiceProvider
{
    use LazyModuleBoot;

    protected string $moduleKey = 'suivi';

    public function boot(): void
    {
        if (!$this->shouldBootModule()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'inovcom-suivi');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations/tenant'),
        ], 'inovcom-suivi-migrations');

        Livewire::component('inovcom-suivi.reports-index', SiteReportsIndex::class);
        Livewire::component('inovcom-suivi.report-form',   SiteReportForm::class);
        Livewire::component('inovcom-suivi.task-board',    TaskBoard::class);

        Route::bind('site_report', fn($value) => SiteReport::on('tenant')->findOrFail($value));

        $this->registerTenantRoutes();
    }

    private function registerTenantRoutes(): void
    {
        Route::prefix('app')
            ->middleware(['web', 'tenant', 'auth:tenant'])
            ->group(function () {
                Route::get('/suivi/board', TaskBoard::class)
                    ->middleware(['module:suivi'])
                    ->name('tenant.suivi.board');

                Route::get('/suivi', fn() => redirect()->route('tenant.suivi.board', ['tenant' => request()->query('tenant') ?? session('tenant_code')]))
                    ->middleware(['module:suivi'])
                    ->name('tenant.suivi.index');

                Route::get('/suivi/create', SiteReportForm::class)
                    ->middleware(['module:suivi'])
                    ->name('tenant.suivi.create');

                Route::get('/suivi/{site_report}/edit', SiteReportForm::class)
                    ->middleware(['module:suivi'])
                    ->name('tenant.suivi.edit');
            });
    }
}
